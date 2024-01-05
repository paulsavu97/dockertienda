<?php

// src/Service/CarritoManager.php

namespace App\Service;

use App\Entity\Producto;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CarritoManager
{
    private $requestStack;
    private $parameterBag;

    public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag)
    {
        $this->requestStack = $requestStack;
        $this->parameterBag = $parameterBag;
    }

    private function getSession(): SessionInterface
    {
        $session = $this->requestStack->getSession();

        if (!$session) {
            throw new \RuntimeException('La sesión no está disponible.');
        }

        return $session;
    }

    public function getCarrito(): array
    {
        $session = $this->getSession();

        return $session->get('carrito', []);
    }

    public function añadirA_Carrito(Producto $producto, int $cantidad = 1): void
    {
        $session = $this->getSession();

        // Verifica si hay una sesión activa
        if (!$session || !$session->isStarted()) {
            return;
        }

        $carrito = $session->get('carrito', []);

        // Verifica si el producto ya está en el carrito
        $productoEnCarrito = array_filter($carrito, function ($elemento) use ($producto) {
            return $elemento['producto']->getId() == $producto->getId();
        });

        if (!empty($productoEnCarrito)) {
            // Si el producto ya está en el carrito, actualiza la cantidad
            foreach ($carrito as &$elemento) {
                if ($elemento['producto']->getId() == $producto->getId()) {
                    $elemento['cantidad'] += $cantidad;
                    break;
                }
            }
        } else {
            // Si el producto no está en el carrito, agrégalo
            $carrito[] = ['producto' => $producto, 'cantidad' => $cantidad];
        }

        $session->set('carrito', $carrito);
    }

    public function eliminarDel_Carrito($id): void
    {
        $session = $this->getSession();

        // Verifica si hay una sesión activa y carrito existente
        if (!$session || !$session->isStarted()) {
            return;
        }

        $carrito = $session->get('carrito');

        // Verifica si hay una sesión activa y carrito existente
        if (!is_array($carrito)) {
            return;
        }

        foreach ($carrito as $index => $elemento) {
            if ($elemento['producto']->getId() == $id) {
                unset($carrito[$index]);
                break;
            }
        }

        // Actualizar el carrito en la sesión
        $session->set('carrito', $carrito);
    }
}
