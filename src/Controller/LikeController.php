<?php

namespace App\Controller;

use App\Entity\Like;
use App\Service\JsonConverter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Post;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class LikeController extends AbstractController
{
    private $jsonConverter;
    private $serializer;

    public function __construct(JsonConverter $jsonConverter, SerializerInterface $serializer)
    {
        $this->jsonConverter = $jsonConverter;
        $this->serializer = $serializer;
    }

    #[Route('/api/like', methods: ['GET'])]
    #[OA\Get(description: 'Retourne les likes')]
    #[OA\Tag(name: 'Likes')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les likes',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "isLike", type: "boolean"),
            ]
        )
    )]
    public function getAllLikes(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $likes = $entityManager->getRepository(Like::class)->findAll();

        // Récupérer les entités User et Post liées à chaque like
        $likeData = [];
        foreach ($likes as $like) {
            // Charger explicitement les entités User et Post si elles sont configurées en lazy loading
            $user = $like->getUser();
            $post = $like->getPost();

            $likeData[] = [
                'id' => $like->getId(),
                'username' => $user ? $user->getUsername() : null, // Ajouter l'ID de l'utilisateur
                'post_id' => $post ? $post->getId() : null, // Ajouter l'ID du post
                'islike' => $like->isIslike(),
            ];
        }

        $data = $this->serializer->serialize(
            $likeData,
            'json',
            [AbstractNormalizer::GROUPS => ['like']]
        );

        return new Response($data);
    }


    #[Route('/api/like/{id}', methods: ['PUT'])]
    #[OA\Put(description: 'Mise à jour des informations du like')]
    #[OA\Tag(name: 'Likes')]
    #[OA\Response(
        response: 201,
        description: 'Like mise a jour avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "isLike", type: "boolean"),
            ]
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
    public function updateLike(int $id, Request $request, ManagerRegistry $doctrine, Security $security): Response
    {
        $entityManager = $doctrine->getManager();
        $like = $entityManager->getRepository(Like::class)->find($id);

        if (!$like) {
            return new Response($this->jsonConverter->encodeToJson(['message' => 'Like non trouvé']), Response::HTTP_NOT_FOUND);
        }

        $user = $security->getUser();

        // Vérifier si l'utilisateur actuel est le propriétaire du like
        if (!$security->isGranted('ROLE_ADMIN') && $user !== $like->getUser()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à mettre à jour ce like.'], Response::HTTP_FORBIDDEN);
        }

        // Update the like based on request data
        $data = json_decode($request->getContent(), true);

        // Assuming you have a 'content' field in the Like entity
        $like->setIslike($data["islike"]);

        $entityManager->flush();

        // Charger explicitement les entités User et Post pour la réponse
        $user = $like->getUser();
        $post = $like->getPost();

        $likeData = [
            'id' => $like->getId(),
            'username' => $user ? $user->getUsername() : null, // Ajouter l'ID de l'utilisateur
            'post_id' => $post ? $post->getId() : null, // Ajouter l'ID du post
            'islike' => $like->isIslike(),
        ];

        $data = $this->serializer->serialize(
            $likeData,
            'json',
            [AbstractNormalizer::GROUPS => ['like']]
        );

        return new Response($data);
    }


    #[Route('/api/like/{id}', methods: ['DELETE'])]
    #[OA\Delete(description: 'Suppression du like')]
    #[OA\Tag(name: 'Likes')]
    #[OA\Response(
        response: 204,
        description: 'Like supprimé avec succès'
    )]
    public function deleteLike(int $id, ManagerRegistry $doctrine, Security $security): Response
    {
        $entityManager = $doctrine->getManager();
        $like = $entityManager->getRepository(Like::class)->find($id);

        if (!$like) {
            return new Response($this->jsonConverter->encodeToJson(['message' => 'Like non trouvé']), Response::HTTP_NOT_FOUND);
        }

        $user = $security->getUser();

        // Vérifier si l'utilisateur actuel est le propriétaire du like
        if (!$security->isGranted('ROLE_ADMIN') && $user !== $like->getUser()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à supprimer ce like.'], Response::HTTP_FORBIDDEN);
        }

        // Charger explicitement les entités User et Post pour la réponse
        $user = $like->getUser();
        $post = $like->getPost();

        $likeData = [
            'id' => $like->getId(),
            'username' => $user ? $user->getUsername() : null, // Ajouter l'ID de l'utilisateur
            'post_id' => $post ? $post->getId() : null, // Ajouter l'ID du post
            'islike' => $like->isIslike(),
        ];

        $data = $this->serializer->serialize(
            $likeData,
            'json',
            [AbstractNormalizer::GROUPS => ['like']]
        );

        // Remove the like
        $entityManager->remove($like);
        $entityManager->flush();

        return new Response($data, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/like', methods: ['POST'])]
    #[OA\Post(description: 'Crée un like')]
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
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "isLike", type: "boolean"),
            ]
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

        // Charger explicitement les entités User et Post pour la réponse
        $user = $like->getUser();
        $post = $like->getPost();

        $likeData = [
            'id' => $like->getId(),
            'username' => $user ? $user->getUsername() : null, // Ajouter l'ID de l'utilisateur
            'post_id' => $post ? $post->getId() : null, // Ajouter l'ID du post
            'islike' => $like->isIslike(),
        ];

        $data = $this->serializer->serialize(
            $likeData,
            'json',
            [AbstractNormalizer::GROUPS => ['like']]
        );

        return new Response($data, Response::HTTP_CREATED);
    }
}
