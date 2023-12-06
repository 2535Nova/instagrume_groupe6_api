<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Service\JsonConverter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security as AnnotationSecurity;
use App\Entity\User;
use App\Entity\Reponse;
use OpenApi\Attributes as OA;


class ReponseController extends AbstractController
{

    private $jsonConverter;

    public function __construct(JsonConverter $jsonConverter)
    {
        $this->jsonConverter = $jsonConverter;
    }

    #[Route('/api/reponse', methods: ['GET'])]
    #[AnnotationSecurity(name: null)]
    #[OA\Tag(name: 'Reponses')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les Reponse',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Reponse::class))
        )
    )]
    public function getAllPosts(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $reponse = $entityManager->getRepository(Reponse::class)->findAll();

        return new Response($this->jsonConverter->encodeToJson($reponse));
    }


    #[Route('/api/reponse/{id}', methods: ['GET'])]
    #[AnnotationSecurity(name: null)]
    #[OA\Tag(name: 'Reponses')]
    #[OA\Response(
        response: 200,
        description: 'Récupérer une reponse par ID',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Reponse::class))
        )
    )]
    public function getCommentaireById(int $id, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $reponse = $entityManager->getRepository(Reponse::class)->find($id);

        if (!$reponse) {
            return new JsonResponse(['error' => 'Reponse non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->jsonConverter->encodeToJson($reponse));
    }


    #[Route('/api/reponse', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "user_id", type: "number"),
                new OA\Property(property: "commentaire_id", type: "number"),
                new OA\Property(property: "content", type: "string"),
            ]
        )
    )]
    #[OA\Tag(name: 'Reponses')]
    #[OA\Response(
        response: 200,
        description: 'Récupérer un commentaire par ID',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Reponse::class))
        )
    )]
    public function addCommentaire(Request $request, ManagerRegistry $doctrine): Response
    {
        $data = json_decode($request->getContent(), true);

        $entityManager = $doctrine->getManager();

        // Récupérez l'utilisateur et le post
        $user = $entityManager->getRepository(User::class)->find($data["user_id"]);
        $commentaire = $entityManager->getRepository(Commentaire::class)->find($data["commentaire_id"]);



        // Créer une nouvelle instance de l'entité reponse
        $reponse = new Reponse();
        $reponse->setUser($user);
        $reponse->setContent($data["content"]);

        // Définir la date et l'heure actuelles
        $reponse->setDate(new \DateTime());

        $reponse->setCommentaire($commentaire);

        // Enregistrez le nouveau commentaire
        $entityManager->persist($reponse);
        $entityManager->flush();

        return new JsonResponse($this->jsonConverter->encodeToJson($reponse), Response::HTTP_CREATED);
    }



    #[Route('/api/reponse/{id}', methods: ['PUT'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "content", type: "string"),
            ]
        )
    )]
    #[OA\Tag(name: 'Reponses')]
    #[OA\Response(
        response: 200,
        description: 'Modifie une reponse par ID',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Reponse::class))
        )
    )]
    public function updateCommentaire(int $id, Request $request, ManagerRegistry $doctrine): Response
{
    $entityManager = $doctrine->getManager();
    $reponse = $entityManager->getRepository(Reponse::class)->find($id);

    if (!$reponse) {
        return new JsonResponse(['error' => 'Reponse non trouvé.'], Response::HTTP_NOT_FOUND);
    }

    $data = json_decode($request->getContent(), true);

    // Mettez à jour les propriétés du commentaire en fonction des données de la requête
    $reponse->setContent($data["content"]);
    // Définir la date et l'heure actuelles
    $reponse->setDate(new \DateTime());

    // Enregistrez les modifications
    $entityManager->flush();

    return new JsonResponse($this->jsonConverter->encodeToJson($reponse));
}
    



    #[Route('/api/reponse/{id}', methods: ['DELETE'])]
    #[OA\Tag(name: 'Reponses')]
    #[OA\Response(
        response: 200,
        description: 'supprime une reponse par ID',
    )]
    public function deleteCommentaire(int $id, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $reponse = $entityManager->getRepository(Reponse::class)->find($id);

        if (!$reponse) {
            return new JsonResponse(['error' => 'Reponse non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        // Supprimez le commentaire
        $entityManager->remove($reponse);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
