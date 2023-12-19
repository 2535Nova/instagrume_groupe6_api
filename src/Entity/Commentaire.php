<?php

namespace App\Entity;

use App\Repository\CommentaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations\Response;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CommentaireRepository::class)]
class Commentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["commentaire"])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["commentaire"])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'commentaires')]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    #[Groups(["commentaire"])]
    private ?Post $post = null;


    #[ORM\Column(length: 255)]
    #[Groups(["commentaire"])]
    private ?string $content = null;

    
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['commentaire'])]
    
    private ?\DateTimeInterface $date = null;

    #[ORM\OneToMany(mappedBy: 'commentaire', targetEntity: Reponse::class, orphanRemoval: true)]
    #[Groups(["commentaire"])]
    private Collection $reponses;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
        $this->users = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }


    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }



    public function getPostId(): ?Post
    {
        return $this->post;
    }

    public function setPostId(?Post $post_id): static
    {
        $this->post = $post_id;

        return $this;
    }

    /**
     * @return Collection<int, Reponse>
     */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(Reponse $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setCommentaire($this);
        }

        return $this;
    }

    public function removeReponse(Reponse $reponse): static
    {
        if ($this->reponses->removeElement($reponse)) {
            // set the owning side to null (unless already changed)
            if ($reponse->getCommentaire() === $this) {
                $reponse->setCommentaire(null);
            }
        }

        return $this;
    }


    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;

        return $this;
    }



    /**
    *@return Collection<int, Users>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }
    public function setUsers($users)
    {
        $this->users = $users;

        return $this;
    }

    public function normalize(NormalizerInterface $normalizer): array
    {
        return [
            'id' => $this->id,
            'user' => $this->getUsers(), // Vous pouvez personnaliser cela selon vos besoins
            'post' => $this->post->getId(), // Idem
            'content' => $this->content,
            'date' => $this->date->format("Y-m-d H:i:s"),
            // ... d'autres propriétés
        ];
    }
}
