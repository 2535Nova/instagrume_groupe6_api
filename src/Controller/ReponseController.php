<?php

namespace App\Controller;

use App\Entity\Commentaire;
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
use App\Entity\Reponse;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;


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
    public function getAllReponses(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $reponses = $entityManager->getRepository(Reponse::class)->findAll();

        // Avant de renvoyer la réponse JSON
        $responseData = array_map(function ($reponse) {
            return [
                'content' => $reponse->getContent(),
                'date' => $reponse->getDate()->format('Y-m-d H:i:s'),
                // ... autres données que vous souhaitez inclure
            ];
        }, $reponses);

        return $this->json($responseData, Response::HTTP_OK);
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
    public function getReponseById(int $id, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $reponse = $entityManager->getRepository(Reponse::class)->find($id);

        if (!$reponse) {
            return new JsonResponse(['error' => 'Reponse non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        // Avant de renvoyer la réponse JSON
        $responseData = [
            'content' => $reponse->getContent(),
            'date' => $reponse->getDate()->format('Y-m-d H:i:s'),
            // ... autres données que vous souhaitez inclure
        ];

        return $this->json($responseData, Response::HTTP_OK);
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
    public function addReponse(Request $request, ManagerRegistry $doctrine, SerializerInterface $serializer): Response
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

        $reponse->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));

        $reponse->setCommentaire($commentaire);

        // Enregistrez le nouveau commentaire
        $entityManager->persist($reponse);
        $entityManager->flush();



        // Utilisez le Serializer pour sérialiser l'entité Reponse en JSON
        $jsonContent = $serializer->serialize($reponse, 'json', [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return $object->getId();
            },
            'datetime_format' => 'Y-m-d H:i:s', // Format de date
        ]);

        // Retournez la réponse JSON
        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
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
    public function updateReponse(int $id, Request $request, ManagerRegistry $doctrine, Security $security,SerializerInterface $serializer): Response
    {
        $entityManager = $doctrine->getManager();
        $reponse = $entityManager->getRepository(Reponse::class)->find($id);

        if (!$reponse) {
            return new JsonResponse(['error' => 'Reponse non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $user = $security->getUser();

        // Vérifier si l'utilisateur actuel est le propriétaire du reponse
        if (!$security->isGranted('ROLE_ADMIN') && $user !== $reponse->getUser()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à supprimer ce commentaire.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Mettez à jour les propriétés du commentaire en fonction des données de la requête
        $reponse->setContent($data["content"]);
        // Définir la date et l'heure actuelles
        $reponse->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));

        // Enregistrez les modifications
        $entityManager->flush();

        // Utilisez le Serializer pour sérialiser l'entité Reponse en JSON
        $jsonContent = $serializer->serialize($reponse, 'json', [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return $object->getId();
            },
            'datetime_format' => 'Y-m-d H:i:s', // Format de date
        ]);

        // Retournez la réponse JSON
        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
    }




    #[Route('/api/reponse/{id}', methods: ['DELETE'])]
    #[OA\Tag(name: 'Reponses')]
    #[OA\Response(
        response: 200,
        description: 'supprime une reponse par ID',
    )]
    public function deleteCommentaire(int $id, ManagerRegistry $doctrine, Security $security): Response
    {
        $entityManager = $doctrine->getManager();
        $reponse = $entityManager->getRepository(Reponse::class)->find($id);

        if (!$reponse) {
            return new JsonResponse(['error' => 'Reponse non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $user = $security->getUser();

        // Vérifier si l'utilisateur actuel est le propriétaire du reponse
        if (!$security->isGranted('ROLE_ADMIN') && $user !== $reponse->getUser()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à supprimer ce commentaire.'], Response::HTTP_FORBIDDEN);
        }

        // Supprimez le commentaire
        $entityManager->remove($reponse);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
