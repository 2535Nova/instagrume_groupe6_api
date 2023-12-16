<?php

namespace App\Entity;

use App\Repository\LikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LikeRepository::class)]
#[ORM\Table(name: '`like`')]
class Like
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\ManyToOne(inversedBy: 'like')]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?User $user = null;

    #[ORM\Column]
    private ?bool $islike = null;


    #[ORM\ManyToOne(inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Post $post = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isIslike(): ?bool
    {
        return $this->islike;
    }

    public function setIslike(bool $islike): static
    {
        $this->islike = $islike;

        return $this;
    }


    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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
    // Dans votre entité Like
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'isLike' => $this->isIslike(),
            // Ajoutez d'autres champs nécessaires
        ];
    }
}
