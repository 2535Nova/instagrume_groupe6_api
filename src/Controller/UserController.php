<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use OpenApi\Attributes as OA;

use App\Service\JsonConverter;
use App\Entity\User;

class UserController extends AbstractController
{

    private $jsonConverter;
    private $passwordHasher;

    public  function __construct(JsonConverter $jsonConverter, UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
        $this->jsonConverter = $jsonConverter;
    }

    #[Route('/api/login', methods: ['POST'])]
    #[Security(name: null)]
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
        description: 'The field used to order rewards',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'L\'utilisateur correspondant a son username',
        content: new OA\JsonContent(ref: new Model(type: User::class))
    )]
    #[OA\Tag(name: 'utilisateurs')]
    public function getUtilisateurByusername(ManagerRegistry $doctrine)
    {
        $request = Request::createFromGlobals();
        $username = $request->query->get('username');
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);


        return new Response($this->jsonConverter->encodeToJson($user));
    }


    #[Route('/api/users', methods: ['GET'])]
    #[OA\Get(description: 'Retourne la liste de tous les utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les utilisateurs',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class))
        )
    )]
    #[OA\Tag(name: 'utilisateurs')]
    public function getAllUsers(ManagerRegistry $doctrine)
    {
        $entityManager = $doctrine->getManager();
        $users = $entityManager->getRepository(User::class)->findAll();

        return new Response($this->jsonConverter->encodeToJson($users));
    }

    #[Route('/api/inscription', methods: ['POST'])]
    #[Security(name: null)]
    #[OA\Post(description: 'inscription')]
    #[OA\Response(
        response: 200,
        description: "insersion d'un user dans la bdd"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'username', type: 'string', default: 'admin'),
                new OA\Property(property: 'password', type: 'string', default: 'password'),
                new OA\Property(property: 'avatar', type: 'string', default: 'avatar')
            ]
        )
    )]
    #[OA\Tag(name: 'utilisateurs')]
    public function createUser(ManagerRegistry $doctrine)
    {
        $input = (array) json_decode(file_get_contents('php://input'), true);

        // Vérifiez la présence des champs nécessaires
        if (empty($input["username"]) || empty($input["password"]) || empty($input["avatar"])) {
            return $this->unprocessableEntityResponse();
        }
        $base64_image = $input["avatar"];

        // Trouver l'extension du format d'image depuis la chaîne base64
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $matches)) {
            $imageFormat = $matches[1];
        
            // Générer un nom de fichier unique en utilisant le nom d'utilisateur
            $imageName = $input["username"] . "." . $imageFormat;
            $destinationPath = "./../public/images/" . $imageName;
            
        
            // Extrait les données de l'image (après la virgule)
            $imageData = substr($base64_image, strpos($base64_image, ',') + 1);
        
            // Décode la chaîne base64 en binaire
            $binaryData = base64_decode($imageData);
        
            if ($binaryData !== false) {
                // Enregistre l'image sur le serveur
                file_put_contents($destinationPath, $binaryData);
            }
        } else {
            // Gestion de l'erreur si le format de l'image n'est pas correct
            // ... (par exemple, renvoyer une réponse d'erreur appropriée)
        }
        
        
        

        $entityManager = $doctrine->getManager();
        $user = new User();
        $user->setUsername($input["username"]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $input["password"]));
        $user->setAvatar($imageName); // Utilisez le nom généré pour l'image
        $user->setRoles(["ROLE_USER"]);
        $user->setBan(false);
        $entityManager->persist($user);
        $entityManager->flush();

        // Utilisez la classe Response de Symfony pour construire la réponse HTTP
        return new Response($this->jsonConverter->encodeToJson($user), Response::HTTP_CREATED);
    
        
    }

    

    #[Route('/api/users/{id}', methods: ['PUT'])]
    #[OA\Put(description: 'Mise à jour des informations de l\'utilisateur')]
    #[OA\Put(security: ["oauth2" => ["ROLE_USER", "ROLE_ADMIN"]])]
    #[OA\Response(
        response: 200,
        description: 'L\'utilisateur mis à jour',
        content: new OA\JsonContent(ref: new Model(type: User::class))
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'password', type: 'string'),
                new OA\Property(property: 'avatar', type: 'string')
            ]
        )
    )]
    #[OA\Tag(name: 'utilisateurs')]
    public function putUser(ManagerRegistry $doctrine, int $id)
    {
        $input = (array) json_decode(file_get_contents('php://input'), true);
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);
    
        // Vérifiez la présence des champs nécessaires
        if (empty($input["username"]) || empty($input["password"]) || empty($input["avatar"])) {
            return $this->unprocessableEntityResponse();
        }
        $base64_image = $input["avatar"];
    
        // Trouver l'extension du format d'image depuis la chaîne base64
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $matches)) {
            $imageFormat = $matches[1];
        
            // Générer un nom de fichier unique en utilisant le nom d'utilisateur
            $imageName = $input["username"] . "." . $imageFormat;
            $destinationPath = "./../public/images/" . $imageName;
    
            // Supprimer l'image existante s'il y en a une
            $existingImagePath = "./../public/images/" . $user->getAvatar();
            if (file_exists($existingImagePath)) {
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
        
        $user->setUsername($input["username"]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $input["password"]));
        $user->setAvatar($imageName); // Utilisez le nom généré pour l'image
        $user->setRoles(["ROLE_USER"]);
        $user->setBan(false);
        $entityManager->persist($user);
        $entityManager->flush();
    
        // Utilisez la classe Response de Symfony pour construire la réponse HTTP
        return new Response($this->jsonConverter->encodeToJson($user), Response::HTTP_CREATED);
    }
    


    #[Route('/api/users/{id}', methods: ['DELETE'])]
    #[OA\Delete(description: 'Suppression de l\'utilisateur')]
    #[Security(name: "ROLE_ADMIN")]
    #[OA\Response(
        response: 204,
        description: 'Utilisateur supprimé avec succès'
    )]
    #[OA\Tag(name: 'utilisateurs')]
    public function deleteUser(ManagerRegistry $doctrine, int $id)
    {
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
            'error' => 'Invalid input'
        ]);
        return $response;
    }
}
