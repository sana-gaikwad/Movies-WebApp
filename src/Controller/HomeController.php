<?php


namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/",name="app_homepage")
     */
    public function homepage(){

        $movies = [
            'Inception',
            'Blood Diamond',
            'Titanic'
        ];
       return $this -> render('home/homepage.html.twig',[
           'welcome' => ucwords("Welcome Sana"),
           'movies' => $movies,
        ]);

    }
}