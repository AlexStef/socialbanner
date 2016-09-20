<?php

namespace Creads\Minisite\Common\Entity;

use Doctrine\ORM\EntityRepository;

class UserRepository extends EntityRepository
{
    public function findOneyEmail($email)
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.email = :email')
            ->setParameter('email', $email)
            ->setMaxResults(1);

        $result = $qb->getQuery()->getResult();

        return isset($result[0]) ? $result[0] : null;
    }
}
