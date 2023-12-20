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
use Symfony\Component\Security\Core\Security;
use App\Entity\User;
use App\Entity\Post;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;


class CommentaireController extends AbstractController
{

    private $jsonConverter;
    private $serializer;

    public function __construct(JsonConverter $jsonConverter, SerializerInterface $serializer)
    {
        $this->jsonConverter = $jsonConverter;
        $this->serializer = $serializer;
    }


    #[Route('/api/commentaire', methods: ['GET'])]
    #[OA\Get(description: 'Retourne les commentaires')]
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les commentaires',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "content", type: "string"),
                new OA\Property(property: "date", type: "string", format: "date-time"),
            ]
        )
    )]
    public function getAllCommentaire(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $commentaires = $entityManager->getRepository(Commentaire::class)->findAll();

        // Récupérer les entités User et Post liées à chaque commentaire
        $commentaireData = [];
        foreach ($commentaires as $commentaire) {
            // Charger explicitement les entités User et Post si elles sont configurées en lazy loading
            $user = $commentaire->getUser();
            $post = $commentaire->getPost();

            $commentaireData[] = [
                'id' => $commentaire->getId(),
                'user_id' => $user ? $user->getId() : null, // Ajouter l'ID de l'utilisateur
                'post_id' => $post ? $post->getId() : null, // Ajouter l'ID du post
                'content' => $commentaire->getContent(),
                'date' => $commentaire->getDate(),
            ];
        }

        $data = $this->serializer->serialize(
            $commentaireData,
            'json',
            [AbstractNormalizer::GROUPS => ['commentaire']]
        );

        return new Response($data);
    }

    #[Route('/api/commentaire/{id}', methods: ['GET'])]
    #[OA\Get(description: 'Retourne le commentaire par son id')]
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Response(
        response: 200,
        description: 'Le Commentaire selon un ID',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "content", type: "string"),
                new OA\Property(property: "date", type: "string", format: "date-time"),
            ]
        )
    )]
    public function getCommentaireById(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();
        $commentaire = $entityManager->getRepository(Commentaire::class)->find($id);

        if (!$commentaire) {
            // Gérer le cas où aucun commentaire n'est trouvé pour l'ID donné
            return new Response('Commentaire non trouvé', 404);
        }

        // Charger explicitement les entités User et Post si elles sont configurées en lazy loading
        $user = $commentaire->getUser();
        $post = $commentaire->getPost();

        // Récupérer les données spécifiques du commentaire
        $commentaireData = [
            'id' => $commentaire->getId(),
            'user_id' => $user ? $user->getId() : null, // Ajouter l'ID de l'utilisateur
            'post_id' => $post ? $post->getId() : null, // Ajouter l'ID du post
            'content' => $commentaire->getContent(),
            'date' => $commentaire->getDate(),
        ];

        $data = $this->serializer->serialize(
            $commentaireData,
            'json',
            [AbstractNormalizer::GROUPS => ['commentaire']]
        );

        return new Response($data);
    }




    #[Route('/api/commentaire', methods: ['POST'])]
    #[OA\Post(description: 'Crée un commentaire')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "user_id", type: "number"),
                new OA\Property(property: "post_id", type: "number"),
                new OA\Property(property: "content", type: "string"),
            ]
        )
    )]
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Response(
        response: 201,
        description: 'Commentaire ajouté avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "content", type: "string"),
                new OA\Property(property: "date", type: "string", format: "date-time"),
            ]
        )
    )]
    public function addCommentaire(Request $request, ManagerRegistry $doctrine): Response
    {
        $data = json_decode($request->getContent(), true);

        $entityManager = $doctrine->getManager();

        // Récupérez l'utilisateur et le post
        $user = $entityManager->getRepository(User::class)->find($data["user_id"]);
        $post = $entityManager->getRepository(Post::class)->find($data["post_id"]);



        // Créer une nouvelle instance de l'entité Commentaire
        $commentaire = new Commentaire();
        $commentaire->setUser($user);   
        $commentaire->setContent($data["content"]);

        // Définir la date et l'heure actuelles
        $commentaire->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));

        $commentaire->setPostId($post);

        // Enregistrez le nouveau commentaire
        $entityManager->persist($commentaire);
        $entityManager->flush();

        return $this->json($commentaire, Response::HTTP_CREATED, [], ['datetime_format' => 'Y-m-d H:i:s']);
    }



    #[Route('/api/commentaire/{id}', methods: ['PUT'])]
    #[OA\Put(description: 'Mise à jour des informations du commentaire')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "content", type: "string"),
            ]
        )
    )]
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Response(
        response: 200,
        description: 'Commentaire mise a jour avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "user_id", type: "integer"),
                new OA\Property(property: "post_id", type: "integer"),
                new OA\Property(property: "content", type: "string"),
                new OA\Property(property: "date", type: "string", format: "date-time"),
            ]
        )
    )]
    public function updateCommentaire(int $id, Request $request, ManagerRegistry $doctrine, Security $security): Response
    {
        $entityManager = $doctrine->getManager();
        $commentaire = $entityManager->getRepository(Commentaire::class)->find($id);

        if (!$commentaire) {
            return new JsonResponse(['error' => 'Commentaire non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        // Récupérer l'utilisateur actuel à partir du token
        $user = $security->getUser();

        // Vérifier si l'utilisateur est autorisé à mettre à jour ce commentaire (vous pouvez adapter cette logique en fonction de vos besoins)
        if (!$security->isGranted('ROLE_ADMIN') && $user !== $commentaire->getUser()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à mettre à jour ce commentaire.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Mettez à jour les propriétés du commentaire en fonction des données de la requête
        $commentaire->setContent($data["content"]);
        // Définir la date et l'heure actuelles
        $commentaire->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));

        // Enregistrez les modifications
        $entityManager->flush();

        return $this->json($commentaire, Response::HTTP_CREATED, [], ['datetime_format' => 'Y-m-d H:i:s']);
    }





    #[Route('/api/commentaire/{id}', methods: ['DELETE'])]
    #[OA\Delete(description: 'Suppression du commentaire')]
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Response(
        response: 204,
        description: 'Commentaire supprimé avec succès',
    )]
    
    public function deleteCommentaire(int $id, ManagerRegistry $doctrine, Security $security): Response
    {
        $entityManager = $doctrine->getManager();
        $commentaire = $entityManager->getRepository(Commentaire::class)->find($id);

        if (!$commentaire) {
            return new JsonResponse(['error' => 'Commentaire non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $user = $security->getUser();

        // Vérifier si l'utilisateur actuel est le propriétaire du commentaire
        if (!$security->isGranted('ROLE_ADMIN') && $user !== $commentaire->getUser()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à supprimer ce commentaire.'], Response::HTTP_FORBIDDEN);
        }

        // Supprimer le commentaire
        $entityManager->remove($commentaire);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
