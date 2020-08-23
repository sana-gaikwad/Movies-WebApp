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
        $url = 'https://www.eventcinemas.com.au/Movies/GetNowShowing';
        $data = file_get_contents($url); //get the json data

        $dataArr = json_decode($data,true); //convert to an associative array
        $conn = $this->getDoctrine()->getConnection();
        $entityManager = $this->getDoctrine()->getManager();
       //dd($dataArr);

        try {
            //Parsing Array to get id, Movie name, year, rating, cast, synopsis, director, genre
            foreach ($dataArr as $key=> $value) {
                if ($key == 'Data') {
                    foreach ($value as $key => $movie) {
                        if($key == 'Movies') {
                            foreach ($movie as $key => $list)
                            {
                                $movieId = 0;
                                $movieName=""; $movieYear = "";$movieCast= ""; $movieDesc=""; $movieGen=""; $movieDirect="";
                                foreach ($list as $key => $details){

                                    if($key == 'Id'){
                                        $movieId = $details;
                                    }
                                    if($key == 'Name'){
                                        $movieName = $details;

                                    }
                                    if ($key == 'ReleasedAt'){
                                        $movieYear = $details;

                                    }
                                    if ($key == 'MainCast'){
                                        $movieCast = $details;

                                    }
                                    if ($key == 'Director'){
                                        $movieDirect = $details;


                                    }
                                    if ($key == 'Genres'){
                                        $movieGen = $details;

                                    }
                                    if ($key == 'Synopsis'){
                                        $movieDesc = $details;
                                    }
                                }

                                //query to check if movie exists in the database
                                $sql = "SELECT * FROM Movie where Movie.code = '$movieId'";
                                $statement = $conn->prepare($sql);
                                $statement->execute();
                                $result = ($statement->fetchAll());

                                //query to check if genre exists
                                $sqlGen = "SELECT * FROM Genre WHERE Genre.type = '$movieGen'";
                                $state = $conn->prepare($sqlGen);
                                $state->execute();
                                $resData = ($state->fetchAll());

                                //query to check if director exists
                                $sqlDirect = ' SELECT * FROM Director WHERE Director.name like "$movieDirect" ' ;
                                $stm = $conn->prepare($sqlDirect);
                                $stm->execute();
                                $res = ($stm->fetchAll());

                                //check if movie exists
                                if (!$result )
                                {
                                    //validating for null values
                                    if (($movieCast && $movieDirect && $movieDesc && $movieName && $movieGen ) != null){

                                        //Adding values to Genre Entity
                                        $newMovie = new Movie();
                                        $newMovie-> setName($movieName);
                                        $newMovie-> setYear($movieYear);
                                        $newMovie -> setRating(0);
                                        $newMovie -> setSynopsis($movieDesc);
                                        $newMovie -> setMainCast($movieCast);
                                        $newMovie -> setCode($movieId);

                                        if (!$res) // if director does not exist then create
                                        {
                                            $director = new Director();
                                            $director->setName($movieDirect);
                                            $director->setSurname(".");
                                            $newMovie->setDirector($director);
                                            $entityManager->persist($director);



                                        }
                                        else{

                                            $dir = $this->getDoctrine()
                                                ->getRepository(Director::class)
                                                ->findDirectorByName($movieDirect);
                                            $newMovie->setDirector($dir);
                                        }

                                        if(!$resData)//if genre does not exist
                                        {
                                            $movieGenre = new Genre();
                                            $movieGenre->setType($movieGen);
                                            $newMovie->addGenre($movieGenre);
                                            $entityManager->persist($movieGenre);

                                        }
                                        else{
                                            $movGen = $this->getDoctrine()
                                                ->getRepository(Genre::class)
                                                ->findGenreByType($movieGen);
                                            $newMovie->addGenre($movGen);
                                        }
                                        $entityManager->persist($newMovie);
                                        $entityManager->flush();

                                    }

                                }

                            }
                        }

                    }

                }

            }
            return new JsonResponse(['status' => 'Customer created!'], Response::HTTP_CREATED);
        }
        catch (Exception $e ){

            echo 'Message: ' .$e->getMessage();
        }

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
