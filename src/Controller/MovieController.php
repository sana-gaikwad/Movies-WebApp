<?php

namespace App\Controller;

use App\Entity\Director;
use App\Entity\Genre;
use App\Entity\Movie;
use App\Form\MovieType;
use App\Form\RateType;
use App\Service\MovieService;
use App\Repository\MovieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/movie")
 */
class MovieController extends AbstractController
{


    /**
     * @Route("/", name="movie_index", methods={"GET"})
     */
    public function index(MovieRepository $movieRepository): Response
    {
        return $this->render('movie/index.html.twig', [
            'movies' => $movieRepository->findAll(),
        ]);
    }

    /**
     * @Route("/list", name="movie_list", methods={"GET"})
     */
    public function list(MovieRepository $movieRepository): Response
    {
        return $this->render('movie/list.html.twig', [
            'movies' => $movieRepository->findAll(),
        ]);
    }

    /**
     * @Route("/rate/{id}", name="movie_rate", methods={"GET","POST"})
     */
    public function rate(Request $request,Movie $movie, $id): Response
    {
        return $this->render('movie/review.html.twig', [
            'movie' => $movie

        ]);
    }

    /**
     * @Route("/review/{id}", name="review_score", methods={"GET","POST"})
     */
    public function review(Request $request, Movie $movie): Response
    {
            $rating = $request->request->get('stars');
            if (empty($rating))
            {
                throw new NotFoundHttpException('Expecting mandatory parameters!');
            }
            $movie->setRating($rating);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($movie);
            $entityManager->flush();
            $this->addFlash('success','Thank You, Your movie rating has been saved');
            return $this->redirectToRoute('movie_list');
    }



    /**
     * @Route("/sync", name="data_sync", methods={"GET","POST"})
     */
    public function sync(Request $request, MovieRepository $movieRepository): JsonResponse
    {
        $url = 'https://www.eventcinemas.com.au/Movies/GetNowShowing?apiKey=klasjdhjaisodh';
        $data = file_get_contents($url); //get the json data

        $dataArr = json_decode($data,true); //convert to an associative array
        $entityManager = $this->getDoctrine()->getManager();

        try {
            if(isset($dataArr["Data"]["Movies"]) )
            {
                foreach ($dataArr["Data"]["Movies"] as $movie) {
                    $newMovie = new Movie();
                    $movieDirector = ""; $movieGenre = "";
                    //Get Movie, see if exists, if not create a new one.

                    if (($movieRepository->findOneBy(['code' => $movie['Id']])) == null) {

                        if (isset($movie['MainCast']) && isset($movie['Id']) && isset($movie['Genres']) && isset($movie['Director'])) {
                            $newMovie->setCode($movie['Id']);
                            $newMovie->setName($movie['Name']);
                            $newMovie->setYear($movie['ReleasedAt']);
                            $newMovie->setMainCast($movie['MainCast']);
                            $newMovie->setSynopsis($movie['Synopsis']);
                            $newMovie->setRating(0);
                            $movieGenre = $movie['Genres'];
                            $movieDirector = $movie['Director'];
                            $entityManager->persist($newMovie);


                            //Check genre +  Director are in DB
                            $newMovie = $this->CreateGenreAndDirectorIfNotExists($newMovie, $movieGenre, $movieDirector);

                            //Check
                            $entityManager->persist($newMovie);
                            $entityManager->flush();
                        }

                    }

                }
            }
            return new JsonResponse(['status' => 'Movies Synced'], Response::HTTP_CREATED);
        }
        catch (Exception $e ){
            echo 'Message: ' .$e->getMessage();
        }

    }

    //
    // This function will look up the director & Genre and create them if they don't exist.
    // It returns the movie object that was sent to it, so we can persist with the correct joins.


    private function CreateGenreAndDirectorIfNotExists($newMovie,$movieGen, $movieDirect){
        $entityManager = $this->getDoctrine()->getManager();

        //query to check if genre exists
        $genreExistsCheck = $this->getDoctrine()->getRepository(Genre::class)->findOneBy(['type' => $movieGen]);

            //query to check if director exists
            $directorExistsCheck = $this->getDoctrine()->getRepository(Director::class)->findOneBy(['name' => $movieDirect]);
            // if director does not exist then add it to database
            if (!$directorExistsCheck)
            {
                $director = new Director();
                $director->setName($movieDirect);
                $director->setSurname(" ");
                $newMovie->setDirector($director);
                $entityManager->persist($director);
                $entityManager->flush();
            }
            else{
                $newMovie->setDirector($directorExistsCheck);
            }

        //if genre does not exist then add it to the database
            // if director does not exist then add it to database
            if (!$genreExistsCheck)
            {
                $movieGenre = new Genre();
                $movieGenre->setType($movieGen);
                $newMovie->addGenre($movieGenre);
                $entityManager ->persist($movieGenre);
                $entityManager->flush();
            }
            else{
                $newMovie-> addGenre($genreExistsCheck);
            }

        return $newMovie;
    }


    /**
     * @Route("/new", name="movie_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $movie = new Movie();
        $form = $this->createForm(MovieType::class, $movie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($movie);
            $entityManager->flush();

            return $this->redirectToRoute('movie_index');
        }

        return $this->render('movie/new.html.twig', [
            'movie' => $movie,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="movie_show", methods={"GET"})
     */
    public function show(Movie $movie): Response
    {
        return $this->render('movie/show.html.twig', [
            'movie' => $movie,
        ]);
    }



    /**
     * @Route("/{id}/edit", name="movie_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Movie $movie): Response
    {

        $form = $this->createForm(MovieType::class, $movie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('movie_index');
        }

        return $this->render('movie/edit.html.twig', [
            'movie' => $movie,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="movie_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Movie $movie): Response
    {
        if ($this->isCsrfTokenValid('delete'.$movie->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($movie);
            $entityManager->flush();
        }

        return $this->redirectToRoute('movie_index');
    }



}
