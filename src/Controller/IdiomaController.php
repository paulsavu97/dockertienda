<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class IdiomaController extends AbstractController
{
    #[Route('/idioma/{_locale}', name: 'cambiar_idioma', methods: ['GET', 'POST'])]
    public function cambiarIdioma(Request $request): Response
    {
        $ruta = $request->request->get('ruta');
        $idioma = $request->getLocale();
    
        // Establecer el idioma en la sesión
        $request->getSession()->set('_locale', $idioma);
    
        if ($ruta) {
            return $this->redirectToRoute($ruta, ['_locale' => $idioma]);
        }
    
        // Verificar si se especificó el idioma 'en' y redirigir a la página de inicio en inglés
        if ($idioma === 'en') {
            return $this->redirectToRoute('app_homepage_index', ['_locale' => $idioma]);
        }
    
        // Redirigir a la página de inicio en español por defecto
        return $this->redirectToRoute('app_homepage_index', ['_locale' => 'es']);
    }
    
}
