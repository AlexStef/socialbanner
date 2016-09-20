<?php

namespace Creads\SocialBanner\Common\Entity;

use Doctrine\ORM\EntityRepository;

class ProjectRepository extends EntityRepository
{
    public function findByUserAndPaymentFailedSinceDate(User $user, $from)
    {
        $qb = $this->createQueryBuilder('p');
        $qb
            ->where($qb->expr()->isNotNull('p.paymentId'))
            ->andWhere('p.status = :notPaid')
            ->andWhere('p.user = :user')
            ->andWhere('p.paymentDate > :fromDate')
            ->setParameter('fromDate', $from)
            ->setParameter('user', $user)
            ->setParameter('notPaid', Project::PAYMENT_FAILED_STATUS)
        ;

        return $qb->getQuery()->getResult();
    }
}
