<?php

namespace App\DataFixtures;

use App\Entity\Commentaire;
use App\Entity\Like;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;


class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        $Admin = new User();
        $Admin->setUsername("root");
        $Admin->setRoles(["ROLE_ADMIN"]);
        $Admin->setPassword("root");
        $Admin->setBan(FALSE);
        $manager->persist($Admin);

        $User = new User();
        $User->setUsername("user");
        $User->setRoles(["ROLE_USER"]);
        $User->setPassword("user");
        $User->setBan(FALSE);
        $manager->persist($User);

        $Test = new User();
        $Test->setUsername("test");
        $Test->setRoles(["ROLE_USER"]);
        $Test->setPassword("test");
        $Test->setBan(TRUE);
        $manager->persist($Test);


        $Post = new Post();
        $Post->setUser($Admin);
        $Post->setIslock(FALSE);
        $Post->setDescription(" ceci est un poste de test");
        $manager->persist($Post);

        $Post2 = new Post();
        $Post2->setUser($User);
        $Post2->setIslock(FALSE);
        $Post2->setDescription(" ceci est un poste 2 !");
        $manager->persist($Post2);

        $Post3 = new Post();
        $Post3->setUser($Test);
        $Post3->setIslock(TRUE);
        $Post3->setDescription(" ceci est un poste verouillé");
        $manager->persist($Post3);

        $Like = new Like();
        $Like->setUser($User);
        $Like->setPost($Post);
        $Like->setIslike(TRUE);
        $manager->persist($Like);

        $Like1 = new Like();
        $Like1->setUser($User);
        $Like1->setPost($Post3);
        $Like1->setIslike(FALSE);
        $manager->persist($Like1);
        
        $Like2 = new Like();
        $Like2->setUser($Test);
        $Like2->setPost($Post2);
        $Like2->setIslike(FALSE);
        $manager->persist($Like2);

        $Like3 = new Like();
        $Like3->setUser($User);
        $Like3->setPost($Post2);
        $Like3->setIslike(FALSE);
        $manager->persist($Like3);

        $commantaire = new Commentaire();
        $commantaire->setUserId($User);
        $commantaire->setPostId($Post);
        $commantaire->setCommentaireId($commantaire);
        $commantaire->setContent("ceci est un test");
        $date = new \DateTime ("2000-02-02");
        $commantaire->setDate($date);
        $manager->persist($commantaire);

        $commantaire1 = new Commentaire();
        $commantaire1->setUserId($User);
        $commantaire1->setPostId($Post3);
        $commantaire1->setCommentaireId($commantaire1);
        $commantaire1->setContent("ceci est le deuxieme commantair");
        $date1 = new \DateTime ("2050-08-10");
        $commantaire1->setDate($date1);
        $manager->persist($commantaire1);

        $commantaire2 = new Commentaire();
        $commantaire2->setUserId($Test);
        $commantaire2->setPostId($Post3);
        $commantaire2->setCommentaireId($commantaire1);
        $commantaire2->setContent("ceci est un test de réponse de commantair");
        $date2 = new \DateTime ("2055-08-10");
        $commantaire2->setDate($date2);
        $manager->persist($commantaire2);

        $manager->flush();
    }
}
