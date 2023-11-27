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
use App\Entity\User;
use App\Entity\Post;

use OpenApi\Attributes as OA;


class CommentaireController extends AbstractController{
    
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
    public function getAllPosts(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $commentaires = $entityManager->getRepository(Commentaire::class)->findAll();

        return new Response($this->jsonConverter->encodeToJson($commentaires));
    }


    #[Route('/api/commentaire/{id}', methods: ['GET'])]
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

        return new JsonResponse($this->jsonConverter->encodeToJson($commentaire));
    }


    #[Route('/api/commentaire', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'content', type: 'string'),
                new OA\Property(
                    property: 'user',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class))
                ),
                new OA\Property(
                    property: 'post',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Post::class))
                ),
                new OA\Property(
                    property: 'commentaire',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Commentaire::class))
                ),
                new OA\Property(property: "date", type: "string")
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
        $data = $this->jsonConverter->decodeFromJSon($request->getContent(), Commentaire::class);

        $entityManager = $doctrine->getManager();

        // Créer une nouvelle instance de l'entité Commentaire
        $commentaire = new Commentaire();
        $commentaire->setUserId($data["user"]);
        $commentaire->setContent($data["content"]);
        $commentaire->setDate($data["date"]);
        $commentaire->setPostId($data["post"]);
        $commentaire->setCommentaireId($data["commentaire"]);

        // Enregistrez le nouveau commentaire
        $entityManager->persist($commentaire);
        $entityManager->flush();

        return new JsonResponse($this->jsonConverter->encodeToJson($commentaire), Response::HTTP_CREATED);
    }


    #[Route('/api/commentaire', methods: ['PUT'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'content', type: 'string'),
                new OA\Property(
                    property: 'user',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class))
                ),
                new OA\Property(
                    property: 'post',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Post::class))
                ),
                new OA\Property(
                    property: 'commentaire',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Commentaire::class))
                ),
                new OA\Property(property: "date", type: "string")
            ]
        )
    )]
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Parameter(
        name: 'id',
        in: 'query',
        description: 'The field used to order rewards',
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Response(
        response: 200,
        description: 'modifie un commentaire par ID',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Commentaire::class))
        )
    )]
    public function updateCommentaire(int $id, Request $request, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $commentaire = $entityManager->getRepository(Commentaire::class)->find($id);

        if (!$commentaire) {
            return new JsonResponse(['error' => 'Commentaire non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->jsonConverter->decodeFromJSon($request->getContent(), Commentaire::class);

        // Mettez à jour les propriétés du commentaire en fonction des données de la requête
        $commentaire->setUserId($data["user"]);
        $commentaire->setContent($data["content"]);
        $commentaire->setDate($data["date"]);
        $commentaire->setPostId($data["post"]);
        $commentaire->setCommentaireId($data["commentaire"]);

        // Enregistrez les modifications
        $entityManager->flush();

        return new JsonResponse($this->jsonConverter->encodeToJson($commentaire));
    }


    #[Route('/api/commentaire/{id}', methods: ['DELETE'])]
    #[OA\Tag(name: 'Commentaires')]
    #[OA\Response(
        response: 200,
        description: 'supprime un commentaire par ID',
    )]
    public function deleteCommentaire(int $id, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $commentaire = $entityManager->getRepository(Commentaire::class)->find($id);

        if (!$commentaire) {
            return new JsonResponse(['error' => 'Commentaire non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        // Supprimez le commentaire
        $entityManager->remove($commentaire);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}

