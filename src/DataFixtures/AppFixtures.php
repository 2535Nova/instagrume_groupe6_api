<?php

namespace App\DataFixtures;

use App\Entity\Commentaire;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;


class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        $User = new User();
        $User->setUsername("root");
        $User->setRoles(["ROLE_ADMIN"]);
        $User->setPassword("root");
        $User->setBan(FALSE);
        $manager->persist($User);

        $Post = new Post();
        $Post->setUser($User);
        $Post->setIslock(FALSE);
        $Post->setDescription(" ceci est un poste de test");
        $manager->persist($Post);

        $commantaire = new Commentaire();
        $commantaire->setUserId($User);
        $commantaire->setPostId($Post);
        $commantaire->setCommentaireId($commantaire);
        $commantaire->setContent("ceci est un test");
        $date = new \DateTime ("2000-02-02");
        $commantaire->setDate($date);
        $manager->persist($commantaire);
        $manager->flush();
    }
}
