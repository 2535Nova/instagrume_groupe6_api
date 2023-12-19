<?php

namespace App\Controller;

use App\Entity\Post;
use App\Service\JsonConverter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class PostController extends AbstractController
{

    private $jsonConverter;
    private $serializer;

    public function __construct(JsonConverter $jsonConverter, SerializerInterface $serializer)
    {
        $this->jsonConverter = $jsonConverter;
        $this->serializer = $serializer;
    }

    #[Route('/api/posts', methods: ['GET'])]
    #[OA\Tag(name: 'Posts')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les posts',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "isLike", type: "boolean"),
            ]
        )
        
    )]
    public function getAllPosts(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $posts = $entityManager->getRepository(Post::class)->findAll();

        // Récupérer les entités User liées à chaque post
        $postData = [];
        foreach ($posts as $post) {
            // Charger explicitement l'entité User si elle est configurée en lazy loading
            $user = $post->getUser();

            $postData[] = [
                'id' => $post->getId(),
                'user_id' => $user ? $user->getId() : null, // Ajouter l'ID de l'utilisateur
                'image' => $post->getImage(),
                'islock' => $post->isIslock(),
                'description' => $post->getDescription(),
            ];
        }

        $data = $this->serializer->serialize(
            $postData,
            'json',
            [AbstractNormalizer::GROUPS => ['post']]
        );

        return new Response($data);
    }

    #[Route('/api/posts/{id}', methods: ['GET'])]
    #[OA\Tag(name: 'Posts')]
    #[OA\Response(
        response: 200,
        description: 'Le Post selon un ID',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "isLike", type: "boolean"),
            ]
        )
    )]
    public function getPostById(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();
        $post = $entityManager->getRepository(Post::class)->find($id);

        if (!$post) {
            // Gérer le cas où aucun post n'est trouvé pour l'ID donné
            return new Response('Post non trouvé', 404);
        }

        // Charger explicitement l'entité User si elle est configurée en lazy loading
        $user = $post->getUser();

        // Récupérer les données spécifiques du post
        $postData = [
            'id' => $post->getId(),
            'user_id' => $user ? $user->getId() : null, // Ajouter l'ID de l'utilisateur
            'image' => $post->getImage(),
            'islock' => $post->isIslock(),
            'description' => $post->getDescription(),
        ];

        $data = $this->serializer->serialize(
            $postData,
            'json',
            [AbstractNormalizer::GROUPS => ['post']]
        );

        return new Response($data);
    }

    #[Route('/api/posts', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "user_id", type: "number"),
                new OA\Property(property: "image", type: "string"),
                new OA\Property(property: "islock", type: "boolean"),
                new OA\Property(property: "description", type: "string"),
            ]
        )
    )]
    #[OA\Tag(name: 'Posts')]
    #[OA\Response(
        response: 201,
        description: 'Post ajouté avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "isLike", type: "boolean"),
            ]
        )
    )]
    public function createPost(ManagerRegistry $doctrine): Response
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);
    
        $entityManager = $doctrine->getManager();
    
        // Récupérez l'utilisateur
        $user = $entityManager->getRepository(User::class)->find($data["user_id"]);
    
        // Créez un nouveau post
        $post = new Post();
        $post->setDescription($data["description"]);
        $post->setIslock($data["islock"]);
        $post->setUser($user);
        $post->setImage($data["image"]);
    
        // Enregistrez le nouveau post
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
                new OA\Property(property: "image", type: "string"),
                new OA\Property(property: "islock", type: "boolean"),
                new OA\Property(property: "description", type: "string"),
            ]
        )
    )]
    #[OA\Tag(name: 'Posts')]
    #[OA\Response(
        response: 200,
        description: 'Post mise a jour avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "isLike", type: "boolean"),
            ]
        )
    )]
    
    public function updatePost(ManagerRegistry $doctrine, int $id, Security $security): Response
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);
    
        $entityManager = $doctrine->getManager();
        $post = $entityManager->getRepository(Post::class)->find($id);
    
        if (!$post) {
            return new Response('Post non trouvé', Response::HTTP_NOT_FOUND);
        }

        $user = $security->getUser();

        // Vérifier si l'utilisateur actuel est le propriétaire du post
        if (!$security->isGranted('ROLE_ADMIN') && $user !== $post->getUser()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à supprimer ce commentaire.'], Response::HTTP_FORBIDDEN);
        }
    
        $post->setDescription($data["description"]);
        $post->setIslock($data["islock"]);
        $post->setImage($data["image"]);
    
        $entityManager->flush();
    
        return new Response($this->jsonConverter->encodeToJson($post));
    }
    
    

    #[Route('/api/posts/{id}', methods: ['DELETE'])]
    #[OA\Response(
        response: 204,
        description: 'Post supprimé avec succès'
    )]
    #[OA\Tag(name: 'Posts')]
    
    public function deletePost(ManagerRegistry $doctrine, int $id, Security $security): Response
    {
        $entityManager = $doctrine->getManager();
        $post = $entityManager->getRepository(Post::class)->find($id);

        if (!$post) {
            return new Response('Post non trouvé');
        }

        $user = $security->getUser();

        // Vérifier si l'utilisateur actuel est le propriétaire du post
        if (!$security->isGranted('ROLE_ADMIN') && $user !== $post->getUser()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à supprimer ce commentaire.'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($post);
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
