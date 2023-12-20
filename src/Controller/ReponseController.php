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
    private $serializer;

    public function __construct(JsonConverter $jsonConverter, SerializerInterface $serializer)
    {
        $this->jsonConverter = $jsonConverter;
        $this->serializer = $serializer;
    }

    #[Route('/api/reponse', methods: ['GET'])]
    #[OA\Get(description: 'Retourne les reponses')]
    #[OA\Tag(name: 'Reponses')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les Reponse',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: "commentaire_id", type: "integer"),
                new OA\Property(property: "content", type: "string"),
                new OA\Property(property: "date",type: "string", format: "date-time"),
            ]
        )  
    )]
    public function getAllReponse(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $reponses = $entityManager->getRepository(Reponse::class)->findAll();

        // Récupérer les entités User liées à chaque réponse
        $reponseData = [];
        foreach ($reponses as $reponse) {
            // Charger explicitement l'entité User si elle est configurée en lazy loading
            $user = $reponse->getUser();
            
            $reponseData[] = [
                'id' => $reponse->getId(),
                'username' => $user ? $user->getUsername() : null, // Ajouter l'ID de l'utilisateur
                'commentaire_id' => $reponse->getCommentaire()->getId(), // Ajouter l'ID du commentaire
                'content' => $reponse->getContent(),
                'date' => $reponse->getDate(),
            ];
        }

        $data = $this->serializer->serialize(
            $reponseData,
            'json',
            [AbstractNormalizer::GROUPS => ['reponse']]
        );

        return new Response($data);
    }


    #[Route('/api/reponse/{id}', methods: ['GET'])]
    #[OA\Get(description: 'Retourne la reponse par son id')]
    #[OA\Tag(name: 'Reponses')]
    #[OA\Response(
        response: 200,
        description: 'La Reponse selon un ID',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: "commentaire_id", type: "integer"),
                new OA\Property(property: "content", type: "string"),
                new OA\Property(property: "date",type: "string", format: "date-time"),
            ]
        )  
    )]
    public function getReponseById(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();
        $reponse = $entityManager->getRepository(Reponse::class)->find($id);

        if (!$reponse) {
            // Gérer le cas où aucune réponse n'est trouvée pour l'ID donné
            return new Response('Réponse non trouvée', 404);
        }

        // Charger explicitement l'entité User si elle est configurée en lazy loading
        $user = $reponse->getUser();

        // Récupérer les données spécifiques de la réponse
        $reponseData = [
            'id' => $reponse->getId(),
            'username' => $user ? $user->getUsername() : null, // Ajouter l'ID de l'utilisateur
            'commentaire_id' => $reponse->getCommentaire()->getId(), // Ajouter l'ID du commentaire
            'content' => $reponse->getContent(),
            'date' => $reponse->getDate(),
        ];

        $data = $this->serializer->serialize(
            $reponseData,
            'json',
            [AbstractNormalizer::GROUPS => ['reponse']]
        );

        return new Response($data);
    }


    #[Route('/api/reponse', methods: ['POST'])]
    #[OA\Post(description: 'Crée une reponse')]
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
        response: 201,
        description: 'Reponse ajouté avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "commentaire_id", type: "integer"),
                new OA\Property(property: "content", type: "string"),
                new OA\Property(property: "date",type: "string", format: "date-time"),
            ]
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
    #[OA\Put(description: 'Mise à jour des informations de la reponse')]
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
        description: 'Reponse mise a jour avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "commentaire_id", type: "integer"),
                new OA\Property(property: "content", type: "string"),
                new OA\Property(property: "date",type: "string", format: "date-time"),
            ]
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
    #[OA\Delete(description: 'Suppression de la reponse')]
    #[OA\Tag(name: 'Reponses')]
    #[OA\Response(
        response: 204,
        description: 'Reponse supprimé avec succès',
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
