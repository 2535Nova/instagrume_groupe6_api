<?php

namespace App\Controller;

use App\Entity\Post;
use App\Service\JsonConverter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security as AnnotationSecurity;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\User;
use OpenApi\Attributes as OA;

class PostController extends AbstractController
{
    private $jsonConverter;

    public function __construct(JsonConverter $jsonConverter)
    {
        $this->jsonConverter = $jsonConverter;
    }

    #[Route('/api/posts', methods: ['GET'])]
    #[AnnotationSecurity(name: null)]
    #[OA\Tag(name: 'Posts')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les posts',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Post::class))
        )
    )]
    public function getAllPosts(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $posts = $entityManager->getRepository(Post::class)->findAll();

        return new Response($this->jsonConverter->encodeToJson($posts));
    }

    #[Route('/api/posts/{id}', methods: ['GET'])]
    #[AnnotationSecurity(name: null)]
    #[OA\Tag(name: 'Posts')]
    #[OA\Response(
        response: 200,
        description: 'Retourne le post corspondant a son id',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Post::class))
        )
    )]
    public function getPostById(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();
        $post = $entityManager->getRepository(Post::class)->find($id);

        if (!$post) {
            return new Response("Post non trouvé");
        }

        return new Response($this->jsonConverter->encodeToJson($post));
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
        response: 200,
        description: 'supprime le post corspondant a son id'
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
