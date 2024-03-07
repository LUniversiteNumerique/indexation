<?php

namespace App\Tests\Entity;

use App\Entity\Groupe;
use App\Repository\GroupeRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GroupeTest extends KernelTestCase
{
    private function getGroupe(): Groupe
    {
        return (new Groupe())->setLabel('Manager')->setRights(['ROLE_CREA_ACTE','ROLE_CREA_NOTI']);
    }

    /** Valide une assertion d'une instance d'Groupe suivant ses contraintes spécifiques */
    private function assertHasErrors(Groupe $group, int $size = 0): void
    {
        self::bootKernel(); $messages = array();

        $errors = $this->getContainer()->get('validator')->validate($group);
        foreach ($errors as $e) $messages[] = sprintf("%s => %s",$e->getPropertyPath(),$e->getMessage());
        $this->assertCount($size,$errors,implode(', ',$messages));
    }

    public function testValidGroupe(): void
    {
        $this->assertHasErrors($this->getGroupe());
    }

    public function testInvalidRightsGroupe(): void
    {
        $this->assertHasErrors($this->getGroupe()->setRights(array()),1);
        $this->assertHasErrors($this->getGroupe()->setRights(['ROLE_USER']),1);
    }

    public function testInvalidLabelGroupe(): void
    {
        $this->assertHasErrors($this->getGroupe()->setLabel(null),1);
        $this->assertHasErrors($this->getGroupe()->setLabel(''),1);
    }


    /** Retourne l'exécution de la méthode $method avec le paramètre $param du repository */
    private function getMockingRep(Groupe $group, string $method, $param=1, $return=null)
    {
        $rep = $this->createMock(GroupeRepository::class);
        $rep->expects(self::once())->method($method)->willReturn($return??$group);

        return $rep->$method($param);
    }

    public function testValidFind(): void
    {
        self::bootKernel();
        $reqGroupe = $this->getGroupe();
        $repGroupe = $this->getMockingRep($reqGroupe,'find');
        $this->assertEquals($reqGroupe->getLabel(), $repGroupe->getLabel());
    }

    public function testValidFindByEmail(): void
    {
        self::bootKernel();
        $reqGroupe = $this->getGroupe();

        $repGroupe = $this->getMockingRep($reqGroupe,'findOneBy',['label' => $reqGroupe->getLabel()]);
        $this->assertInstanceOf(Groupe::class, $repGroupe);
        $this->assertEquals($reqGroupe, $repGroupe);
    }

    public function testInvalidFindByEmail(): void
    {
        self::bootKernel();
        $reqGroupe = $this->getGroupe();

        /** @var Groupe $repGroupe */
        $repGroupe = $this->getMockingRep($reqGroupe,'findOneBy',['label' => $reqGroupe->getLabel()]);
        $this->assertSame($reqGroupe, $repGroupe->setEditeLe(new \DateTime()));
        $this->assertObjectHasProperty('id', $repGroupe->setEditeLe(new \DateTime()));
    }
}