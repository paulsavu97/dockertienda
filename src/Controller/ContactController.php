<?php

// src/Controller/ContactController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ContactController extends AbstractController
{
    public function submitForm(Request $request, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            // Recuperar datos del formulario
            $name = $request->request->get('name');
            $emailValue = $request->request->get('email');
            $subject = $request->request->get('subject');
            $messageContent = $request->request->get('message');

            // Verificar si $emailValue es una cadena no vacía y válida antes de usarlo
            if (!empty($emailValue) && is_string($emailValue) && filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                // Crear el objeto Email con la dirección de correo electrónico válida
                $email = (new Email())
                    ->from($emailValue)
                    ->to('soporte.zenglow.es@gmail.com')
                    ->subject($subject)
                    ->html("Nombre: $name <br>Email: $emailValue <br>Mensaje: $messageContent");

                // Enviar el correo electrónico
                $mailer->send($email);

                // Redirigir a una página de éxito o donde desees
                return $this->redirectToRoute('app_homepage_index');
            }

            // Manejar el caso donde la dirección de correo electrónico no es válida o está vacía
            return new Response('Dirección de correo electrónico no válida o está vacía', 400);
        }

        // Manejar el caso donde el formulario no se envió correctamente
        return new Response('Error en el envío del formulario', 400);
    }
}
