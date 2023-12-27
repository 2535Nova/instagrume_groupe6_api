<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Nelmio\ApiDocBundle\Annotation\Model;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use OpenApi\Attributes as OA;
use App\Service\JsonConverter;
use App\Entity\User;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Security\Core\User\UserInterface;


class UserController extends AbstractController
{

    private $jsonConverter;
    private $passwordHasher;
    private $serializer;


    public  function __construct(JsonConverter $jsonConverter, UserPasswordHasherInterface $passwordHasher, SerializerInterface $serializer)
    {
        $this->passwordHasher = $passwordHasher;
        $this->jsonConverter = $jsonConverter;
        $this->serializer = $serializer;
    }

    #[Route('/api/login', methods: ['POST'])]
    #[OA\Post(description: 'Connexion à l\'API')]
    #[OA\Response(
        response: 200,
        description: 'Un token'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'username', type: 'string', default: 'admin'),
                new OA\Property(property: 'password', type: 'string', default: 'password')
            ]
        )
    )]
    #[OA\Tag(name: 'utilisateurs')]
    public function logUser(ManagerRegistry $doctrine, JWTTokenManagerInterface $JWTManager)
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || $data == null || empty($data['username']) || empty($data['password'])) {
            return new Response('Identifiants invalides', 401);
        }

        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $data['username']]);

        if (!$user) {
            throw $this->createNotFoundException();
        }
        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return new Response('Identifiants invalides', 401);
        }

        $token = $JWTManager->create($user);
        return new JsonResponse(['token' => $token]);
    }

    #[Route('/api/myself', methods: ['GET'])]
    #[OA\Get(description: 'Retourne l\'utilisateur authentifié')]
    #[OA\Response(
        response: 200,
        description: 'L\'utilisateur correspondant au token passé dans le header',
        content: new OA\JsonContent(ref: new Model(type: User::class))
    )]
    #[OA\Tag(name: 'utilisateurs')]
    public function getUtilisateur(JWTEncoderInterface $jwtEncoder, Request $request)
    {
        $tokenString = str_replace('Bearer ', '', $request->headers->get('Authorization'));

        $user = $jwtEncoder->decode($tokenString);

        return new Response($this->jsonConverter->encodeToJson($user));
    }

    #[Route('/api/users/search', methods: ['GET'])]
    #[OA\Get(description: 'Retourne l\'utilisateur par son username')]
    #[OA\Parameter(
        name: 'username',
        in: 'query',
        description: 'L\'utilisateur correspondant au nom passé en paramètre URL',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'L\'utilisateur correspondant a son username',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: 'roles', type: 'string', default: '["ROLE_USER"]'),
                new OA\Property(property: "avatar", type: "string"),
                new OA\Property(property: "ban", type: "boolean"),
            ]
        )
    )]
    #[OA\Tag(name: 'utilisateurs')]
    public function getUtilisateurByusername(ManagerRegistry $doctrine, Request $request)
    {


        $request = Request::createFromGlobals();
        $username = $request->query->get('username');
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);


        return new Response($this->jsonConverter->encodeToJson($user));
    }

    #[Route('/api/users/{id}', methods: ['GET'])]
    #[OA\Get(description: 'Retourne l\'utilisateur par son id')]
    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'Le User selon un ID',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: 'roles', type: 'string', default: '["ROLE_USER"]'),
                new OA\Property(property: "avatar", type: "string"),
                new OA\Property(property: "ban", type: "boolean"),
            ]
        )
    )]
    public function getUserById(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            // Gérer le cas où aucun utilisateur n'est trouvé pour l'ID donné
            return new Response('Utilisateur non trouvé', 404);
        }

        // Récupérer les données spécifiques de l'utilisateur
        $userData = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'avatar' => $user->getAvatar(),
            'ban' => $user->isBan(),
            // Ajoutez d'autres champs si nécessaire
        ];

        $data = $this->serializer->serialize(
            $userData,
            'json',
            [AbstractNormalizer::GROUPS => ['user']]
        );

        return new Response($data);
    }


    #[Route('/api/users', methods: ['GET'])]
    #[OA\Get(description: 'Retourne la liste de tous les utilisateurs')]
    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les utilisateurs',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: 'roles', type: 'string', default: '["ROLE_USER"]'),
                new OA\Property(property: "avatar", type: "string"),
                new OA\Property(property: "ban", type: "boolean"),
            ]
        )
    )]
    public function getAllUsers(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $users = $entityManager->getRepository(User::class)->findAll();

        // Récupérer les données spécifiques de chaque utilisateur
        $userData = [];
        foreach ($users as $user) {
            $userData[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
                'avatar' => $user->getAvatar(), // Assuming there is a getAvatar() method
                'ban' => $user->isBan(),       // Assuming there is a getBan() method
                // Ajoutez d'autres champs si nécessaire
            ];
        }

        $data = $this->serializer->serialize(
            $userData,
            'json',
            [AbstractNormalizer::GROUPS => ['user']]
        );

        return new Response($data);
    }

    #[Route('/api/users/{id}/posts', methods: ['GET'])]
    #[OA\Get(description: 'Retourne tous les post liés à l\'utilisateur')]
    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les post liés à l\'utilisateur',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "islike", type: "boolean"),
            ]
        )
    )]
    public function getUserComments(ManagerRegistry $doctrine, int $id, SerializerInterface $serializer): Response
    {
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return new Response('Utilisateur non trouvé', 404);
        }

        // Récupérer les commentaires associés à l'utilisateur
        $posts = $user->getPosts();

        // Construire un tableau de données à sérialiser
        $commentData = [];
        foreach ($posts as $post) {
            $commentData[] = [
                'id' => $post->getId(),
                'user_id' => $user ? $user->getId() : null, // Ajouter l'ID de l'utilisateur
                'image' => $post->getImage(),
                'islock' => $post->isIslock(),
                'description' => $post->getDescription(),
                // Ajoutez d'autres champs si nécessaire
            ];
        }

        // Utiliser le serializer pour convertir le tableau de données en JSON
        $data = $serializer->serialize(
            $commentData,
            'json',
            [AbstractNormalizer::GROUPS => ['comment']]
        );

        return new Response($data);
    }



    #[Route('/api/users/{id}/like', methods: ['GET'])]
    #[OA\Get(description: 'Retourne tous les likes liés à l\'utilisateur')]
    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les likes liés à l\'utilisateur',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "image", type: "string"),
                new OA\Property(property: "islock", type: "boolean"),
                new OA\Property(property: "description", type: "string"),
            ]
        )
    )]
    public function getUserLike(ManagerRegistry $doctrine, int $id, SerializerInterface $serializer): Response
    {
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return new Response('Utilisateur non trouvé', 404);
        }

        // Récupérer les likes associés à l'utilisateur
        $likes = $user->getLikes();

        // Construire un tableau de données à sérialiser
        $likeData = [];
        foreach ($likes as $like) {
            $likeData[] = [
                'id' => $like->getId(),
                'user_id' => $user ? $user->getId() : null, // Ajouter l'ID de l'utilisateur
                'post_id' => $like->getPost() ? $like->getPost()->getId() : null, // Ajouter l'ID du post
                'islike' => $like->isIslike(),
                // Ajoutez d'autres champs si nécessaire
            ];
        }

        // Utiliser le serializer pour convertir le tableau de données en JSON
        $data = $serializer->serialize(
            $likeData,
            'json',
            [AbstractNormalizer::GROUPS => ['like']]
        );

        return new Response($data);
    }


    #[Route('/api/inscription', methods: ['POST'])]
    #[OA\Post(description: 'inscription')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'username', type: 'string', default: 'admin'),
                new OA\Property(property: 'password', type: 'string', default: 'password')
            ]
        )
    )]
    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 201,
        description: 'User ajouté avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: 'roles', type: 'string', default: '["ROLE_USER"]'),
                new OA\Property(property: "avatar", type: "string"),
                new OA\Property(property: "ban", type: "boolean"),
            ]
        )
    )]
    public function createUser(ManagerRegistry $doctrine)
    {
        $input = (array) json_decode(file_get_contents('php://input'), true);

        // Vérifiez la présence des champs nécessaires
        if (!empty($input["username"]) || !empty($input["password"])) {
            $entityManager = $doctrine->getManager();
            $user = new User();
            $user->setUsername($input["username"]);
            $user->setPassword($this->passwordHasher->hashPassword($user, $input["password"]));
            $user->setAvatar("null"); // Utilisez le nom généré pour l'image
            $user->setRoles(["ROLE_USER"]);
            $user->setBan(false);
            $entityManager->persist($user);
            $entityManager->flush();
            // Utilisez la classe Response de Symfony pour construire la réponse HTTP
            return new Response($this->jsonConverter->encodeToJson($user), Response::HTTP_CREATED);
        }
        return new Response("Les champs username et password doivent etre rempli", Response::HTTP_BAD_REQUEST);
    }


    #[Route('/api/users/{id}', methods: ['PUT'])]
    #[OA\Put(description: 'Mise à jour des informations de l\'utilisateur')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'roles', type: 'string', default: 'ROLE_USER'),
                new OA\Property(property: 'ban', type: 'boolean', default: 'false')
            ]
        )
    )]
    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'L\'utilisateur mis à jour avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: "roles", type: "string"),
                new OA\Property(property: "ban", type: "boolean"),
            ]
        )
    )]
    public function putUser(ManagerRegistry $doctrine, int $id, Security $security)
    {

        // Vérifie si l'utilisateur a le rôle "ROLE_ADMIN"
        if (!$security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Vous n\'êtes pas autorisé à utiliser cette fonction.');
        }

        $input = (array) json_decode(file_get_contents('php://input'), true);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Erreur JSON : ' . json_last_error_msg()], Response::HTTP_BAD_REQUEST);
        }

        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);

        // Vérifiez la présence des champs nécessaires
        if (!isset($input["roles"]) && !isset($input["ban"])) {
            return $this->json(['error' => 'Le rôle et la valeur de ban sont requises'], Response::HTTP_BAD_REQUEST);
        }
        $roles = $input["roles"];

        // Vérifier si $roles est une chaîne, puis le convertir en tableau si nécessaire
        if (is_string($roles)) {
            $roles = [$roles];
        }

        $user->setRoles($roles);

        $user->setRoles($roles);
        $user->setBan($input["ban"]);
        $entityManager->persist($user);
        $entityManager->flush();

        // Construire le tableau associatif avec les données à retourner
        $avatarData = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'ban' => $user->isBan(),
            // Ajoutez d'autres champs si nécessaire
        ];

        // Sérialiser le tableau associatif en JSON
        $serializedData = $this->serializer->serialize(
            $avatarData,
            'json',
            [AbstractNormalizer::GROUPS => ['avatar']]
        );

        // Retourner la réponse HTTP
        return new Response($serializedData, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[Route('/api/users/{id}/password', methods: ['PUT'])]
    #[OA\Put(description: 'Mise à jour des informations de l\'utilisateur')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'password', type: 'string'),
            ]
        )
    )]
    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'L\'utilisateur mis à jour avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
            ]
        )
    )]
    public function putPassword(ManagerRegistry $doctrine, int $id, JWTEncoderInterface $jwtEncoder, Request $request)
    {

        $input = (array) json_decode(file_get_contents('php://input'), true);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Erreur JSON : ' . json_last_error_msg()], Response::HTTP_BAD_REQUEST);
        }

        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);

        $tokenString = str_replace('Bearer ', '', $request->headers->get('Authorization'));

        $tokenuser = $jwtEncoder->decode($tokenString);
        $usernameToken = $tokenuser['username'];
        if ($usernameToken !== $user->getUsername()) {
            // Retourner une réponse d'erreur indiquant que l'utilisateur ne peut pas être modifié
            return $this->json(['error' => 'Vous n\'êtes pas autorisé à modifier cet utilisateur.'], Response::HTTP_FORBIDDEN);
        }
        // Vérifiez la présence des champs nécessaires
        if (empty($input["password"])) {
            return $this->json(['error' => 'Le mot de passe est requis.'], Response::HTTP_BAD_REQUEST);
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $input["password"]));    
        //$user->setAvatar($imageName);
        $entityManager->persist($user);
        $entityManager->flush();

        // Construire le tableau associatif avec les données à retourner
        $avatarData = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            // Ajoutez d'autres champs si nécessaire
        ];

        // Sérialiser le tableau associatif en JSON
        $serializedData = $this->serializer->serialize(
            $avatarData,
            'json',
            [AbstractNormalizer::GROUPS => ['avatar']]
        );

        // Retourner la réponse HTTP
        return new Response($serializedData, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }



    #[Route('/api/users/{id}/avatar', methods: ['PUT'])]
    #[OA\Put(description: 'Mise à jour des informations de l\'utilisateur')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'avatar', type: 'string'),
            ]
        )
    )]
    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'L\'utilisateur mis à jour avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: "avatar", type: "string"),
            ]
        )
    )]
    public function putAvatar(ManagerRegistry $doctrine, int $id, JWTEncoderInterface $jwtEncoder, Request $request)
    {
        $input = (array) json_decode(file_get_contents('php://input'), true);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Erreur JSON : ' . json_last_error_msg()], Response::HTTP_BAD_REQUEST);
        }

        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);

        $tokenString = str_replace('Bearer ', '', $request->headers->get('Authorization'));

        $tokenuser = $jwtEncoder->decode($tokenString);
        $usernameToken = $tokenuser['username'];
        if ($usernameToken !== $user->getUsername()) {
            // Retourner une réponse d'erreur indiquant que l'utilisateur ne peut pas être modifié
            return $this->json(['error' => 'Vous n\'êtes pas autorisé à modifier cet utilisateur.'], Response::HTTP_FORBIDDEN);
        }



        // Vérifiez la présence des champs nécessaires
        if (empty($input["avatar"])) {
            return $this->json(['error' => 'un avatar est requis.'], Response::HTTP_BAD_REQUEST);
        }
        $base64_image = $input["avatar"];

        // Trouver l'extension du format d'image depuis la chaîne base64
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $matches)) {
            $imageFormat = $matches[1];

            // Générer un nom de fichier unique en utilisant le nom d'utilisateur

            $imageName = $user->getUsername() . "." . $imageFormat;
            $destinationPath = "./../public/images/user/" . $imageName;

            // Supprimer l'image existante s'il y en a une
            $existingImagePath = "./../public/images/user/" . $user->getAvatar();
            if (file_exists($existingImagePath) && is_file($existingImagePath)) {
                unlink($existingImagePath);
            }

            // Extrait les données de l'image (après la virgule)
            $imageData = substr($base64_image, strpos($base64_image, ',') + 1);

            // Décode la chaîne base64 en binaire
            $binaryData = base64_decode($imageData);

            if ($binaryData !== false) {
                // Enregistre la nouvelle image sur le serveur
                file_put_contents($destinationPath, $binaryData);
            }
        } else {
            // Gestion de l'erreur si le format de l'image n'est pas correct
            // ... (par exemple, renvoyer une réponse d'erreur appropriée)
        }

        $user->setAvatar($imageName);
        $entityManager->persist($user);
        $entityManager->flush();

        // Construire le tableau associatif avec les données à retourner
        $avatarData = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'avatar' => $user->getAvatar(),         // Ajoutez d'autres champs si nécessaire
        ];

        // Sérialiser le tableau associatif en JSON
        $serializedData = $this->serializer->serialize(
            $avatarData,
            'json',
            [AbstractNormalizer::GROUPS => ['avatar']]
        );

        // Retourner la réponse HTTP
        return new Response($serializedData, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }






    #[Route('/api/users/{id}', methods: ['DELETE'])]
    #[OA\Delete(description: 'Suppression de l\'utilisateur')]
    #[OA\Response(
        response: 204,
        description: 'Utilisateur supprimé avec succès'
    )]
    #[OA\Tag(name: 'utilisateurs')]
    public function deleteUser(ManagerRegistry $doctrine, int $id,Security $security)
    {

        // Vérifie si l'utilisateur a le rôle "ROLE_ADMIN"
        if (!$security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Vous n\'êtes pas autorisé à utiliser cette fonction.');
        }
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        $entityManager->remove($user);
        $entityManager->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
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
