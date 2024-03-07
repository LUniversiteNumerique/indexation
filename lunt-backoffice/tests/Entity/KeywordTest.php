<?php

namespace App\Tests\Entity;

use App\Entity\Keyword;
use App\Repository\KeywordRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class KeywordTest extends KernelTestCase
{
    private function getKeyword(): Keyword
    {
        return (new Keyword())->setNom('DOE')->setValide(false);
    }

    /** Valide une assertion d'une instance d'Keyword suivant ses contraintes spécifiques */
    private function assertHasErrors(Keyword $key, int $size = 0): void
    {
        self::bootKernel(); $messages = array();

        $errors = $this->getContainer()->get(ValidatorInterface::class)->validate($key);
        foreach ($errors as $e) $messages[] = sprintf("%s => %s",$e->getPropertyPath(),$e->getMessage());
        $this->assertCount($size,$errors,implode(', ',$messages));
    }

    public function testValidKeyword(): void
    {
        $this->assertHasErrors($this->getKeyword());
    }

    public function testInvalidValidKeyword(): void
    {
        $this->assertHasErrors($this->getKeyword()->setValide(null));
        $this->assertHasErrors($this->getKeyword()->setValide('0'));
        $this->assertHasErrors($this->getKeyword()->setValide(1));
    }

    public function testInvalidNameKeyword(): void
    {
        $this->assertHasErrors($this->getKeyword()->setNom(null),1);
        $this->assertHasErrors($this->getKeyword()->setNom(''),1);
    }


    /** Retourne l'exécution de la méthode $method avec le paramètre $param du repository */
    private function getMockingRep(Keyword $key, string $method, $param=1, $return=null)
    {
        $rep = $this->createMock(KeywordRepository::class);
        $rep->expects(self::once())->method($method)->willReturn($return??$key);

        return $rep->$method($param);
    }

    public function testValidFind(): void
    {
        self::bootKernel();
        $reqKwd = $this->getKeyword();
        $repKwd = $this->getMockingRep($reqKwd,'find');
        $this->assertEquals($reqKwd->isValide(), $repKwd->isValide());
    }

    public function testValidFindByName(): void
    {
        self::bootKernel();
        $reqKwd = $this->getKeyword();

        $repKwd = $this->getMockingRep($reqKwd,'findOneBy',['nom' => $reqKwd->getNom()]);
        $this->assertInstanceOf(Keyword::class, $repKwd);
        $this->assertEquals($reqKwd, $repKwd);
    }

    public function testInvalidFindByName(): void
    {
        self::bootKernel();
        $reqKwd = $this->getKeyword();

        /** @var Keyword $repKwd */
        $repKwd = $this->getMockingRep($reqKwd,'findOneBy',['nom' => $reqKwd->getNom()]);
        $this->assertSame($reqKwd, $repKwd->setEditeLe(new \DateTime()));
        $this->assertObjectHasProperty('id', $repKwd->setEditeLe(new \DateTime()));
    }
}