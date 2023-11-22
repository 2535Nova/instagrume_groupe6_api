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


    private function createUser()
    {
        $input = (array) json_decode(file_get_contents('php://input'), true);
        if (empty($input["username"]) || empty($input["password"]) || empty($input["avatar"])) {
            return $this->unprocessableEntityResponse();
        }
        // La chaîne image base64
        $base64_image = $input["avatar"];

        // Fonction pour déterminer le format de l'image en fonction des premiers octets
        // Utilise preg_match pour extraire le format de l'image depuis la chaîne base64
        if (preg_match('#^.*?base64,#', $base64_image, $matches)) {
            $imageFormat = $matches[0]; // Obtient le format de l'image

            // Divise la chaîne en utilisant la virgule comme séparateur
            $parts = explode(';', $base64_image);

            if (count($parts) > 0) {
                // Extrayez la partie finale après la dernière barre oblique
                $imageFormat = end(explode('/', $parts[0]));

                // Génère un nom de fichier unique
                $imageName = $input["nom"] . "." . $imageFormat;

                // Spécifie le chemin de destination pour enregistrer l'image
                $destinationPath = "src/images/" . $imageName;

                // Extrait les données de l'image (après la virgule)
                $imageData = substr($base64_image, strpos($base64_image, ',') + 1);

                // Décode la chaîne base64 en binaire
                $binaryData = base64_decode($imageData);
            }

            if ($binaryData !== false) {
                // Enregistre l'image sur le serveur
                file_put_contents($destinationPath, $binaryData);
            }


            $user = new User($input["username"], $input["password"], $input["avatar"]);
            


            $response['status_code_header'] = $_SERVER['SERVER_PROTOCOL'] . ' 201 Created';
            $response['body'] = json_encode($user);
            return $response;
        }
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
