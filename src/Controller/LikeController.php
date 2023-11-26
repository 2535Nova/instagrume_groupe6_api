<?php

namespace App\Controller;

use App\Entity\Like;
use App\Service\JsonConverter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Nelmio\ApiDocBundle\Annotation\Model;
use App\Entity\Post;
use App\Entity\USer;
use OpenApi\Attributes as OA;

class LikeController extends AbstractController
{
    private $jsonConverter;

    public function __construct(JsonConverter $jsonConverter)
    {
        $this->jsonConverter = $jsonConverter;
    }

    #[Route('/api/like', methods: ['GET'])]
    #[OA\Tag(name: 'Likes')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les likes',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Like::class))
        )
    )]
    public function getAllPosts(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $likes = $entityManager->getRepository(Like::class)->findAll();

        return new Response($this->jsonConverter->encodeToJson($likes));
    }

    #[Route('/api/like/{id}', methods: ['PUT'])]
    #[OA\Tag(name: 'Likes')]
    #[OA\Response(
        response: 200,
        description: 'Like mis à jour avec succès',
        content: new OA\JsonContent(
            type: 'object',
            example: '{"message": "Like mis à jour avec succès"}'
        )
    )]
    public function updateLike(int $id, Request $request, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $like = $entityManager->getRepository(Like::class)->find($id);

        if (!$like) {
            return new Response($this->jsonConverter->encodeToJson(['message' => 'Like non trouvé']), Response::HTTP_NOT_FOUND);
        }

        // Update the like based on request data
        $data = $this->jsonConverter->decodeFromJSon($request->getContent(), Like::class);
        // Assuming you have a 'content' field in the Like entity
        $like->setUser($data["user"]);
        $like->setIslike($data["islike"]);
        $like->setPost($data["post"]);


        $entityManager->flush();

        return new Response($this->jsonConverter->encodeToJson(['message' => 'Like mis à jour avec succès']));
    }

    #[Route('/api/like/{id}', methods: ['DELETE'])]
    #[OA\Tag(name: 'Likes')]
    #[OA\Response(
        response: 204,
        description: 'Like supprimé avec succès'
    )]
    public function deleteLike(int $id, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $like = $entityManager->getRepository(Like::class)->find($id);

        if (!$like) {
            return new Response($this->jsonConverter->encodeToJson(['message' => 'Like non trouvé']), Response::HTTP_NOT_FOUND);
        }

        // Remove the like
        $entityManager->remove($like);
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/like', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'post',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Post::class))
                ),
                new OA\Property(
                    property: 'user',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class))
                ),
                new OA\Property(property: "islike", type: "boolean")
            ]
        )
    )]
    #[OA\Tag(name: 'Likes')]
    #[OA\Response(
        response: 201,
        description: 'Like créé avec succès',
        content: new OA\JsonContent(
            type: 'object',
            example: '{"message": "Like créé avec succès"}'
        )
    )]
    public function createLike(Request $request, ManagerRegistry $doctrine): Response
    {
        $data = $this->jsonConverter->decodeFromJSon($request->getContent(), Like::class);

        $like = new Like();
        $like->setUser($data["user"]);
        $like->setIslike($data["islike"]);
        $like->setPost($data["post"]);

        // Persist and flush the entity
        $entityManager = $doctrine->getManager();
        $entityManager->persist($like);
        $entityManager->flush();

        return new Response($this->jsonConverter->encodeToJson(['message' => 'Like créé avec succès']), Response::HTTP_CREATED);
    }
}
