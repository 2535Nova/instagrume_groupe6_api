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
use Symfony\Component\Security\Core\Security;
use App\Entity\User;
use App\Entity\Post;
use Nelmio\ApiDocBundle\Annotation\Security as AnnotationSecurity;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class CommentaireController extends AbstractController
{

    private $jsonConverter;

    public function __construct(JsonConverter $jsonConverter)
    {
        $this->jsonConverter = $jsonConverter;
    }

    #[Route('/api/commentaire', methods: ['GET'])]
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Response(
        response: 200,
        description: 'La liste de tous les commentaires',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Commentaire::class))
        )
    )]
    public function getAllCommentaire(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $commentaires = $entityManager->getRepository(Commentaire::class)->findAll();
        return new Response($this->jsonConverter->encodeToJson($commentaires));
    }

    #[Route('/api/commentaire/{id}', methods: ['GET'])]
    #[AnnotationSecurity(name: null)]
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Response(
        response: 200,
        description: 'Récupérer un commentaire par ID',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Commentaire::class))
        )
    )]
    public function getCommentaireById(int $id, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $commentaire = $entityManager->getRepository(Commentaire::class)->find($id);

        if (!$commentaire) {
            return new JsonResponse(['error' => 'Commentaire non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        // Avant de renvoyer la réponse JSON
        $responseData = [
            'content' => $commentaire->getContent(),
            'date' => $commentaire->getDate()->format('Y-m-d H:i:s'),
            // ... autres données que vous souhaitez inclure
        ];

        return $this->json($responseData, Response::HTTP_OK);
    }


    #[Route('/api/commentaire', methods: ['POST'])]
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
        response: 200,
        description: 'Récupérer un commentaire par ID',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Commentaire::class))
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
        $commentaire->setUsers($user);
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
        description: 'Modifie un commentaire par ID',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Commentaire::class))
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
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Response(
        response: 200,
        description: 'supprime un commentaire par ID',
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
