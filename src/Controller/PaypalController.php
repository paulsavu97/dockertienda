<?php
// src/Controller/PaypalController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use App\Service\CarritoManager;
use App\Service\ComprasManager;
use App\Entity\User;
use PayPal\Api\PaymentExecution;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PaypalController extends AbstractController
{
    private $apiContext;
    private $carritoManager;
    private $comprasManager;
    private $parameterBag;

    public function __construct(
        CarritoManager $carritoManager,
        ComprasManager $comprasManager,
        ParameterBagInterface $parameterBag
    ) {
        $this->carritoManager = $carritoManager;
        $this->comprasManager = $comprasManager;
        $this->parameterBag = $parameterBag;

        // Configuración del contexto de la API de PayPal
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(
                $this->parameterBag->get('paypal_client_id'),
                $this->parameterBag->get('paypal_secret')
            )
        );
        $this->apiContext->setConfig([
            'mode' => 'sandbox', // Cambia a 'live' en producción
            'http.ConnectionTimeOut' => 30,
        ]);
    }

    #[Route('/paypal/create-payment', name: 'paypal_create_payment', methods: ['POST', 'GET'])]
    public function createPayment(Request $request): Response
    {
        try {
            // Lógica para obtener los productos del carrito y calcular el total
            $carrito = $this->carritoManager->getCarrito();

            if (empty($carrito)) {
                throw new \RuntimeException('El carrito está vacío.');
            }

            // Crear un array de ítems para el botón de PayPal
            $items = [];
            foreach ($carrito as $producto) {
                $item = new Item();
                $item->setName($producto['producto']->getNombre())
                    ->setCurrency('EUR')
                    ->setQuantity($producto['cantidad'])
                    ->setPrice($producto['producto']->getPrecio());

                $items[] = $item;
            }

            // Crear un objeto ItemList y asignar los ítems
            $itemList = new ItemList();
            $itemList->setItems($items);

            // Crear un objeto Amount con el total del carrito
            $total_carrito = $this->calcularTotal($carrito); // Obtener el total del carrito

            $amount = new Amount();
            $amount->setCurrency('EUR')
                ->setTotal($total_carrito);

            // Crear un objeto Transaction con la descripción y el número de factura
            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription("Descripción de la transacción")
                ->setInvoiceNumber(uniqid());

            // Configurar las URLs de redirección
            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl($this->generateUrl('paypal_execute_payment', [], UrlGeneratorInterface::ABSOLUTE_URL))
                ->setCancelUrl($this->generateUrl('paypal_cancel_payment', [], UrlGeneratorInterface::ABSOLUTE_URL));

            // Crear un objeto Payer
            $payer = new Payer();
            $payer->setPaymentMethod("paypal");

            // Crear un objeto Payment y asignarle la configuración anterior
            $payment = new Payment();
            $payment->setIntent("sale")
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions([$transaction]);

            // Crear el pago en PayPal y redirigir a la plantilla con el botón de PayPal
            $payment->create($this->apiContext);
            
            return $this->render('paypal/create_payment.html.twig', [
                'approval_link' => $payment->getApprovalLink(),
                'total_carrito' => $total_carrito, // Pasar la variable total_carrito a la plantilla
                'paypal_client_id' => $this->parameterBag->get('paypal_client_id'),
            ]);
        } catch (\Exception $ex) {
            // Manejar errores
            return $this->render('paypal/error.html.twig', [
                'error' => $ex->getMessage(),
            ]);
        }
    }



    public function executePayment(Request $request): Response
{
    try {
        $paymentId = $request->query->get('paymentId');
        $payerId = $request->query->get('PayerID');

        $payment = Payment::get($paymentId, $this->apiContext);
        $execution = new PaymentExecution();
        $execution->setPayerId($payerId);

        $result = $payment->execute($execution, $this->apiContext);

        if ($result->getState() === 'approved') {
            $usuario = $this->getUser();

            if ($usuario) {
                $pedido = $this->comprasManager->crearNuevoPedido($usuario);
                $this->comprasManager->registrarCompras_Pedido($pedido);

                return $this->redirectToRoute('paypal_success_payment'); // Cambiado a 'paypal_success_payment'
            } else {
                return $this->render('paypal/error.html.twig', [
                    'error' => 'Usuario no autenticado.',
                ]);
            }
        } else {
            return $this->render('paypal/error.html.twig', [
                'error' => 'Pago no aprobado por PayPal.',
            ]);
        }
    } catch (\Exception $ex) {
        return $this->render('paypal/error.html.twig', [
            'error' => $ex->getMessage(),
        ]);
    }
}

#[Route('/paypal/success-payment', name: 'paypal_success_payment', methods: ['POST', 'GET'])]
public function successPayment(): Response
{
    return $this->render('paypal/success.html.twig');
}


    #[Route('/paypal/cancel-payment', name: 'paypal_cancel_payment', methods: ['POST', 'GET'])]
    public function cancelPayment(): Response
    {
        return $this->render('paypal/cancel.html.twig');
    }

    private function calcularTotal(array $carrito): float
    {
        $total = 0;
        foreach ($carrito as $producto) {
            $total += $producto['cantidad'] * $producto['producto']->getPrecio();
        }
        return $total;
    }
}

