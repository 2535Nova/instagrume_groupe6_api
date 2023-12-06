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
use Nelmio\ApiDocBundle\Annotation\Security as AnnotationSecurity;
use App\Entity\Post;
use App\Entity\User;
use OpenApi\Attributes as OA;

class LikeController extends AbstractController
{
    private $jsonConverter;

    public function __construct(JsonConverter $jsonConverter)
    {
        $this->jsonConverter = $jsonConverter;
    }

    #[Route('/api/like', methods: ['GET'])]
    #[AnnotationSecurity(name: null)]
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
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "islike", type: "boolean")
            ]
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
        $data = json_decode($request->getContent(), true);
    
        // Assuming you have a 'content' field in the Like entity
        $like->setIslike($data["islike"]);
    
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
                new OA\Property(property: "user_id", type: "number"),
                new OA\Property(property: "post_id", type: "number"),
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
        $entityManager = $doctrine->getManager();
        $data = json_decode($request->getContent(), true);
    
        // Vérifiez que les clés attendues sont présentes dans le tableau $data
        if (!isset($data["user_id"]) || !isset($data["islike"]) || !isset($data["post_id"])) {
            return new Response($this->jsonConverter->encodeToJson(['message' => 'Donnees manquantes']), Response::HTTP_BAD_REQUEST);
        }
    
        // Récupérez l'utilisateur et le post
        $user = $entityManager->getRepository(User::class)->find($data["user_id"]);
        $post = $entityManager->getRepository(Post::class)->find($data["post_id"]);
    
        // Vérifiez que l'utilisateur et le post existent
        if (!$user || !$post) {
            return new Response($this->jsonConverter->encodeToJson(['message' => 'Utilisateur ou post non trouve']), Response::HTTP_NOT_FOUND);
        }
    
        // Créez l'objet Like en utilisant les entités récupérées
        $like = new Like();
        $like->setUser($user);
        $like->setIslike($data["islike"]);
        $like->setPost($post);
    
        // Persistez et flush l'entité
        $entityManager->persist($like);
        $entityManager->flush();
    
        return new Response($this->jsonConverter->encodeToJson(['message' => 'Like cree avec succes']), Response::HTTP_CREATED);
    }
    
}
