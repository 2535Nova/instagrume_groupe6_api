<?php

namespace App\Controller;

use App\Entity\Post;
use App\Service\JsonConverter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;

class PostController extends AbstractController
{
    private $jsonConverter;

    public function __construct(JsonConverter $jsonConverter)
    {
        $this->jsonConverter = $jsonConverter;
    }

    #[Route('/api/posts', methods: ['GET'])]
    #[OA\Tag(name: 'Posts')]
    public function getAllPosts(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $posts = $entityManager->getRepository(Post::class)->findAll();

        return new Response($this->jsonConverter->encodeToJson($posts));
    }

    #[Route('/api/posts/{id}', methods: ['GET'])]
    #[OA\Tag(name: 'Posts')]
    public function getPostById(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();
        $post = $entityManager->getRepository(Post::class)->find($id);

        if (!$post) {
            return new Response("No Post found");
        }

        return new Response($this->jsonConverter->encodeToJson($post));
    }

    #[Route('/api/posts', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'image', type: 'file'),
                new OA\Property(property: 'user_id', type: 'int'),
                new OA\Property(property: "description", type: "string"),
                new OA\Property(property: "islock", type: "boolean")
            ]
        )
    )]
    #[OA\Tag(name: 'Posts')]    
    public function createPost(ManagerRegistry $doctrine): Response
    {
        $request= Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $entityManager = $doctrine->getManager();

        $post = new Post();
        $post->setDescription($data["description"]);
        $post->setIslock($data["islock"]);
        $post->setUser($data["user_id"]);
        $post->setImage($data["image"]);

        $entityManager->persist($post);
        $entityManager->flush();

        return new Response($this->jsonConverter->encodeToJson($post), Response::HTTP_CREATED);
    }

    #[Route('/api/posts/{id}', methods: ['PUT'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'image', type: 'file'),
                new OA\Property(
                    property: 'user',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'name', type: 'string')
                        ]
                    )
                ),
                new OA\Property(property: "description", type: "string"),
                new OA\Property(property: "islock", type: "boolean")
            ]
        )
    )]
    #[OA\Tag(name: 'Posts')]
    public function updatePost(ManagerRegistry $doctrine, int $id): Response
    {
        $request= Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $entityManager = $doctrine->getManager();
        $post = $entityManager->getRepository(Post::class)->find($id);

        if (!$post) {
            return new Response('Post not found');
        }

        $post->setDescription($data["description"]);
        $post->setIslock($data["islock"]);
        $post->setUser($data["user"]);
        $post->setImage($data["image"]);

        $entityManager->flush();

        return new Response($this->jsonConverter->encodeToJson($post));
    }

    #[Route('/api/posts/{id}', methods: ['DELETE'])]
    #[OA\Tag(name: 'Posts')]
    public function deletePost(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();
        $post = $entityManager->getRepository(Post::class)->find($id);

        if (!$post) {
            return new Response('Post not found');
        }

        $entityManager->remove($post);
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
