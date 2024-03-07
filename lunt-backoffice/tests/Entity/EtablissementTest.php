<?php

namespace App\Tests\Entity;

use App\Entity\Etablissement;
use App\Repository\EtablissementRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EtablissementTest extends KernelTestCase
{
    private function getEtab(): Etablissement
    {
        return (new Etablissement())->setAbrege('John')->setNom('DOE')->setEmail('john@doe.eu');
    }

    /** Valide une assertion d'une instance d'Etablissement suivant ses contraintes spécifiques */
    private function assertHasErrors(Etablissement $etab, int $size = 0): void
    {
        self::bootKernel(); $messages = array();

        $errors = $this->getContainer()->get(ValidatorInterface::class)->validate($etab);
        foreach ($errors as $e) $messages[] = sprintf("%s => %s",$e->getPropertyPath(),$e->getMessage());
        $this->assertCount($size,$errors,implode(', ',$messages));
    }

    public function testValidEtablissement(): void
    {
        $this->assertHasErrors($this->getEtab());
    }

    public function testInvalidEmailEtablissement(): void
    {
        $this->assertHasErrors($this->getEtab()->setEmail(null),1);
        $this->assertHasErrors($this->getEtab()->setEmail('joHn doc'),1);
    }

    public function testInvalidNameEtablissement(): void
    {
        $this->assertHasErrors($this->getEtab()->setNom(null),1);
        $this->assertHasErrors($this->getEtab()->setAbrege(''),1);
    }


    /** Retourne l'exécution de la méthode $method avec le paramètre $param du repository */
    private function getMockingRep(Etablissement $etab, string $method, $param=1, $return=null)
    {
        $rep = $this->createMock(EtablissementRepository::class);
        $rep->expects(self::once())->method($method)->willReturn($return??$etab);

        return $rep->$method($param);
    }

    public function testValidFind(): void
    {
        self::bootKernel();
        $reqEtab = $this->getEtab();
        $repEtab = $this->getMockingRep($reqEtab,'find');
        $this->assertEquals($reqEtab->getNom(), $repEtab->getNom());
    }

    public function testValidFindByEmail(): void
    {
        self::bootKernel();
        $reqEtab = $this->getEtab();

        $repEtab = $this->getMockingRep($reqEtab,'findOneBy',['email' => $reqEtab->getEmail()]);
        $this->assertInstanceOf(Etablissement::class, $repEtab);
        $this->assertEquals($reqEtab, $repEtab);
    }

    public function testInvalidFindByEmail(): void
    {
        self::bootKernel();
        $reqEtab = $this->getEtab();

        /** @var Etablissement $repEtab */
        $repEtab = $this->getMockingRep($reqEtab,'findOneBy',['email' => $reqEtab->getEmail()]);
        $this->assertSame($reqEtab, $repEtab->setEditeLe(new \DateTime()));
        $this->assertObjectHasProperty('id', $repEtab->setEditeLe(new \DateTime()));
    }
}