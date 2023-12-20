<?php

namespace App\DataFixtures;

use App\Entity\Commentaire;
use App\Entity\Like;
use App\Entity\Post;
use App\Entity\Reponse;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;


class AppFixtures extends Fixture
{
    private $passwordHasher;
    public  function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    public function load(ObjectManager $manager): void
    {

        $serializer = new Serializer(array(new DateTimeNormalizer()));
        $Admin = new User();
        $Admin->setUsername("root");
        $Admin->setRoles(["ROLE_ADMIN"]);
        $Admin->setPassword($this->passwordHasher->hashPassword($Admin, 'root'));
        $Admin->setBan(FALSE);
        $manager->persist($Admin);

        $User = new User();
        $User->setUsername("user");
        $User->setRoles(["ROLE_USER"]);
        $User->setPassword($this->passwordHasher->hashPassword($User, 'user'));
        $User->setBan(FALSE);
        $manager->persist($User);

        $Test = new User();
        $Test->setUsername("test");
        $Test->setRoles(["ROLE_USER"]);
        $Test->setPassword($this->passwordHasher->hashPassword($Test, 'test'));
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
        $commantaire->setUser($User);
        $commantaire->setPostId($Post);
        $commantaire->setContent("ceci est un test");
        $date = $serializer->denormalize('2016-01-01T00:00:00+00:00', \DateTime::class);
        $commantaire->setDate($date);
        $manager->persist($commantaire);

        $commantaire1 = new Commentaire();
        $commantaire1->setUser($User);
        $commantaire1->setPostId($Post3);
        $commantaire1->setContent("ceci est le deuxieme commantair");
        $date1 = $serializer->denormalize('2050-08-10T00:00:00+00:00', \DateTime::class);
        $commantaire1->setDate($date1);
        $manager->persist($commantaire1);

        $commantaire2 = new Commentaire();
        $commantaire2->setUser($Admin);
        $commantaire2->setPostId($Post3);
        $commantaire2->setContent("ceci est un test de réponse de commantair");
        $date2 = $serializer->denormalize('2055-08-10T00:00:00+00:00', \DateTime::class);
        $commantaire2->setDate($date2);
        $manager->persist($commantaire2);

        $response1 = new Reponse();
        $response1->setCommentaire($commantaire2);
        $response1->setContent("Alpha");
        $response1->setUser($Admin);
        $response1->setDate($date);
        $manager->persist($response1);

        $response2 = new Reponse();
        $response2->setCommentaire($commantaire2);
        $response2->setContent("Beta");
        $response2->setUser($User);
        $response2->setDate($date1);
        $manager->persist($response2);

        $response3 = new Reponse();
        $response3->setCommentaire($commantaire);
        $response3->setContent("Omega");
        $response3->setUser($Test);
        $response3->setDate($date2);
        $manager->persist($response3);

        

        $manager->flush();
    }
}
