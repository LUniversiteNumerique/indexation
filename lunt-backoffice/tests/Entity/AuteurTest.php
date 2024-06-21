<?php

namespace App\Tests\Entity;

use App\Entity\Auteur;
use App\Repository\AuteurRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuteurTest extends KernelTestCase
{
    private function getAuteur(): Auteur
    {
        return (new Auteur())->setPrenom('John')->setNom('DOE')->setEmail('john@doe.eu');
    }

    /** Valide une assertion d'une instance d'Auteur suivant ses contraintes spécifiques */
    private function assertHasErrors(Auteur $auteur, int $size = 0): void
    {
        self::bootKernel(); $messages = array();

        $errors = $this->getContainer()->get('validator')->validate($auteur);
        foreach ($errors as $e) $messages[] = sprintf("%s => %s",$e->getPropertyPath(),$e->getMessage());
        $this->assertCount($size,$errors,implode(', ',$messages));
    }

    public function testValidAuteur(): void
    {
        $this->assertHasErrors($this->getAuteur());
    }

    public function testInvalidEmailAuteur(): void
    {
        $this->assertHasErrors($this->getAuteur()->setEmail(null),1);
        $this->assertHasErrors($this->getAuteur()->setEmail('joHn doc'),1);
    }

    public function testInvalidNameAuteur(): void
    {
        $this->assertHasErrors($this->getAuteur()->setNom(null),1);
        $this->assertHasErrors($this->getAuteur()->setPrenom(''),1);
    }


    /** Retourne l'exécution de la méthode $method avec le paramètre $param du repository */
    private function getMockingRep(Auteur $auteur, string $method, $param=1, $return=null)
    {
        $rep = $this->createMock(AuteurRepository::class);
        $rep->expects(self::once())->method($method)->willReturn($return??$auteur);

        return $rep->$method($param);
    }

    public function testValidFind(): void
    {
        self::bootKernel();
        $reqAuteur = $this->getAuteur();
        $repAuteur = $this->getMockingRep($reqAuteur,'find');
        $this->assertEquals($reqAuteur->getName(), $repAuteur->getName());
    }

    public function testValidFindByEmail(): void
    {
        self::bootKernel();
        $reqAuteur = $this->getAuteur();

        $repAuteur = $this->getMockingRep($reqAuteur,'findOneBy',['email' => $reqAuteur->getEmail()]);
        $this->assertInstanceOf(Auteur::class, $repAuteur);
        $this->assertEquals($reqAuteur, $repAuteur);
    }

    public function testInvalidFindByEmail(): void
    {
        self::bootKernel();
        $reqAuteur = $this->getAuteur();

        /** @var Auteur $repAuteur */
        $repAuteur = $this->getMockingRep($reqAuteur,'findOneBy',['email' => $reqAuteur->getEmail()]);
        $this->assertSame($reqAuteur, $repAuteur->setEditeLe(new \DateTime()));
        $this->assertObjectHasProperty('id', $repAuteur->setEditeLe(new \DateTime()));
    }
}