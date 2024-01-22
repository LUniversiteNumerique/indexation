<?php

namespace App\Tests\Entity;

use App\Entity\{Notice,User};
use Monolog\Test\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class NoticeSecurityTest extends TestCase
{
    private function createUser(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }

    public function provideCases()
    {
        yield 'anonymous cannot edit' => [
            'edit',
            new Notice($this->createUser(1)),
            null,
            Voter::ACCESS_DENIED
        ];

        yield 'non-owner cannot edit' => [
            'edit',
            new Notice($this->createUser(1)),
            $this->createUser(2),
            Voter::ACCESS_DENIED
        ];

        yield 'owner can edit' => [
            'edit',
            new Notice($this->createUser(1)),
            $this->createUser(1),
            Voter::ACCESS_GRANTED
        ];
    }

    /**
     * @dataProvider provideCases
     */
    public function testVote(
        string $attribute,
        Notice $project,
        ?User $user,
        $expectedVote
    ) {
        $voter = new NoticeVoter();

        $token = new AnonymousToken('secret', 'anonymous');
        if ($user) {
            $token = new UsernamePasswordToken(
                $user, 'credentials', 'memory'
            );
        }

        $this->assertSame(
            $expectedVote,
            $voter->vote($token, $project, [$attribute])
        );
    }
}