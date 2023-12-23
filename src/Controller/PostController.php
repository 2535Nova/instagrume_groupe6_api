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
use Symfony\Component\Filesystem\Filesystem;
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
    #[OA\Get(description: 'Retourne les posts')]
    #[OA\Tag(name: 'Posts')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les posts',
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
                'username' => $user ? $user->getUsername() : null, // Ajouter l'ID de l'utilisateur
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
    #[OA\Get(description: 'Retourne le post par son id')]
    #[OA\Tag(name: 'Posts')]
    #[OA\Response(
        response: 200,
        description: 'Le Post selon un ID',
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
            'username' => $user ? $user->getUsername() : null, // Ajouter l'ID de l'utilisateur
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
    #[OA\Post(description: 'Crée un post')]
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
                new OA\Property(property: "user_id", type: "number"),
                new OA\Property(property: "image", type: "string"),
                new OA\Property(property: "islock", type: "boolean"),
                new OA\Property(property: "description", type: "string")
            ]
        )
    )]
    public function createPost(ManagerRegistry $doctrine): Response
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        // Vérifiez la présence des champs nécessaires
        if (empty($data["user_id"]) || empty($data["image"]) || empty($data["description"])) {
            return $this->unprocessableEntityResponse();
        }

        // Récupérez l'utilisateur
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($data["user_id"]);

        // Gérer le cas où l'utilisateur n'est pas trouvé
        if (!$user) {
            return new Response('Utilisateur non trouvé', 404);
        }

        $base64_image = $data["image"];

        // Trouver l'extension du format d'image depuis la chaîne base64
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $matches)) {
            $imageFormat = $matches[1];

            // Récupérer le dernier ID enregistré pour les posts et l'incrémenter de 1
            $lastPostId = $entityManager->getRepository(Post::class)->createQueryBuilder('p')
                ->select('MAX(p.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $newPostId = $lastPostId + 1;

            // Générer un nom de fichier unique en utilisant le nom d'utilisateur et le nouvel ID
            $imageName = $user->getUsername() . "Post" . $newPostId . "." . $imageFormat;
            $destinationPath = "./../public/images/post/" . $imageName;

            // Extrait les données de l'image (après la virgule)
            $imageData = substr($base64_image, strpos($base64_image, ',') + 1);

            // Décode la chaîne base64 en binaire
            $binaryData = base64_decode($imageData);

            if ($binaryData !== false) {
                // Enregistre l'image sur le serveur
                file_put_contents($destinationPath, $binaryData);
            }
        } else {
            return new Response('Image invalide', 401);
        }

        // Créez un nouveau post
        $post = new Post();
        $post->setDescription($data["description"]);
        $post->setIslock($data["islock"]);
        $post->setUser($user);
        $post->setImage($imageName);

        // Enregistrez le nouveau post
        $entityManager->persist($post);
        $entityManager->flush();

        // Charger explicitement l'entité User si elle est configurée en lazy loading
        $user = $post->getUser();

        // Récupérer les données spécifiques du post
        $postData = [
            'id' => $post->getId(),
            'username' => $user ? $user->getUsername() : null,
            'image' => $post->getImage(),
            'islock' => $post->isIslock(),
            'description' => $post->getDescription(),
        ];

        $serializedData = $this->serializer->serialize(
            $postData,
            'json',
            [AbstractNormalizer::GROUPS => ['post']]
        );

        return new Response($serializedData, Response::HTTP_CREATED, ['Content-Type' => 'application/json']);
    }


    #[Route('/api/posts/{id}', methods: ['PUT'])]
    #[OA\Put(description: 'Mise à jour des informations du post')]
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
        if (!$this->isAuthorizedToUpdatePost($security, $post, $user)) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à mettre à jour ce post.'], Response::HTTP_FORBIDDEN);
        }

        // Gérer la base64 si elle est présente dans les données
        if (!empty($data["image"])) {
            $base64_image = $data["image"];

            // Trouver l'extension du format d'image depuis la chaîne base64
            if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $matches)) {
                $imageFormat = $matches[1];

                // Générer un nom de fichier unique en utilisant le nom d'utilisateur et l'ID du post
                $imageName = $post->getUser()->getUsername() . "Post" . $post->getId() . "." . $imageFormat;
                $destinationPath = "./../public/images/post/" . $imageName;

                // Supprimer l'ancienne image si elle existe
                $filesystem = new Filesystem();
                $oldImagePath = "./../public/images/post/" . $post->getImage();
                if ($filesystem->exists($oldImagePath)) {
                    $filesystem->remove($oldImagePath);
                }

                // Extrait les données de l'image (après la virgule)
                $imageData = substr($base64_image, strpos($base64_image, ',') + 1);

                // Décode la chaîne base64 en binaire
                $binaryData = base64_decode($imageData);

                if ($binaryData !== false) {
                    // Enregistre l'image sur le serveur
                    file_put_contents($destinationPath, $binaryData);
                    // Mettre à jour le champ image dans l'entité Post
                    $post->setImage($imageName);
                } else {
                    return new Response('Image invalide', 401);
                }
            } else {
                return new Response('Image invalide', 401);
            }
        }

        // Mettre à jour les autres champs du post
        $post->setDescription($data["description"]);
        $post->setIslock($data["islock"]);

        $entityManager->flush();

        // Charger explicitement l'entité User si elle est configurée en lazy loading
        $user = $post->getUser();

        // Récupérer les données spécifiques du post
        $postData = [
            'id' => $post->getId(),
            'username' => $user ? $user->getUsername() : null,
            'image' => $post->getImage(),
            'islock' => $post->isIslock(),
            'description' => $post->getDescription(),
        ];

        $serializedData = $this->serializer->serialize(
            $postData,
            'json',
            [AbstractNormalizer::GROUPS => ['post']]
        );

        return new Response($serializedData, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    private function isAuthorizedToUpdatePost(Security $security, Post $post, $user): bool
    {
        // Adapter la logique d'autorisation en fonction de vos besoins
        return $security->isGranted('ROLE_ADMIN') || $user === $post->getUser();
    }



    #[Route('/api/posts/{id}', methods: ['DELETE'])]
    #[OA\Delete(description: 'Suppression du post')]
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
    private function unprocessableEntityResponse()
    {
        $response['status_code_header'] = $_SERVER['SERVER_PROTOCOL'] . ' 422 Unprocessable Entity';
        $response['body'] = json_encode([
            'error' => 'Données invalid'
        ]);
        return $response;
    }
}
