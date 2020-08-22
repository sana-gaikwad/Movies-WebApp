<?php


namespace App\Service;
use PhpParser\Node\Expr\Array_;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class MovieService
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getMoviesApi(): object
    {
        $response = $this->httpClient->request('GET',
            'https://www.eventcinemas.com.au/Movies/GetNowShowing');

        $contentType = $response->getHeaders()['content-type'][0]; // check  content-type
        //dd($contentType);

        $data = $response->getContent();

        $decoded = json_decode($data);

        return $decoded;


    }
}