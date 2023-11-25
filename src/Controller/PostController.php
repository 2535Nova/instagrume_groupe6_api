<?php
namespace App\Controller;

use App\Entity\Post;
use App\Service\JsonConverter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;

class PostController extends AbstractController{

    private $jsonConverter;

    public  function __construct(JsonConverter $jsonConverter) {
        $this->jsonConverter= $jsonConverter;
    }
    
    #[Route('/api/posts', methods: ['GET'])]
    #[OA\Tag(name: 'Posts')]
    public function getAllPosts(ManagerRegistry $doctrine){
        $entityManager= $doctrine->getManager();
        $posts= $entityManager->getRepository(Post::class)->findAll();
        
        return new Response($this->jsonConverter->encodeToJson($posts));
    }

    #[Route('/api/posts/{id}', methods: ['GET'])]
    #[OA\Tag(name: 'Posts')]
    public function getPostById(ManagerRegistry $doctrine, int $id){
        $entityManager= $doctrine->getManager();
        $post= $entityManager->getRepository(Post::class)->find($id);
        
        return new Response($this->jsonConverter->encodeToJson($post));
    }
}
