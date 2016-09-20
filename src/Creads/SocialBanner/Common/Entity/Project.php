<?php

namespace Creads\SocialBanner\Common\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="ProjectRepository")
 * @ORM\Table(name="project",indexes={@ORM\Index(name="project_status_idx", columns={"status"})})
 */
class Project
{
    const PAID_STATUS = 'project.status.paid';
    const NOT_PAID_STATUS = 'project.status.not_paid';
    const PAYMENT_FAILED_STATUS = 'project.status.failed';
    const WAITING_FOR_PAYMENT_STATUS = 'project.status.waiting';
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="partners_gid", type="string", length=255)
     */
    protected $partnersGid;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    /**
     * @var string
     * @ORM\Column(name="payment_id", type="string", length=255, nullable=true)
     */
    protected $paymentId = null;

    /**
     * @var string
     * @ORM\Column(name="status", type="string")
     */
    protected $status = self::NOT_PAID_STATUS;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="payment_date", type="datetime", nullable=true)
     */
    protected $paymentDate = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime('now');
    }

    /**
     * Gets the value of id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets the gid of the Partners project.
     *
     * @return string
     */
    public function getPartnersGid()
    {
        return $this->partnersGid;
    }

    /**
     * Sets the value of partnersGid.
     *
     * @param string $partnersGid the Partners project gid
     *
     * @return self
     */
    public function setPartnersGid($partnersGid)
    {
        $this->partnersGid = $partnersGid;

        return $this;
    }

    /**
     * Gets the value of user.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Sets the value of user.
     *
     * @param User $user the user
     *
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Gets the value of paymentId.
     *
     * @return string
     */
    public function getPaymentId()
    {
        return $this->paymentId;
    }

    /**
     * Sets the value of paymentId.
     *
     * @param string $paymentId the payment id
     *
     * @return self
     */
    public function setPaymentId($paymentId)
    {
        $this->paymentId = $paymentId;

        return $this;
    }

    /**
     * Gets the value of status.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Checks if the project is paid.
     *
     * @return bool
     */
    public function isPaid()
    {
        return ($this->status === self::PAID_STATUS);
    }

    /**
     * Sets the value of status.
     *
     * @param string $status the status
     *
     * @return self
     */
    public function setStatus($status)
    {
        if (in_array($status, [self::NOT_PAID_STATUS, self::PAID_STATUS, self::WAITING_FOR_PAYMENT_STATUS, self::PAYMENT_FAILED_STATUS])) {
            $this->status = $status;
        }

        return $this;
    }

    public function hasPaymentError()
    {
        return ($this->status === self::PAYMENT_FAILED_STATUS);
    }

    /**
     * Gets the value of createdAt.
     *
     * @return \DateTime $created
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Sets the value of createdAt.
     *
     * @param \DateTime $created $createdAt the created at
     *
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Gets the value of paymentDate.
     *
     * @return \DateTime $paymentDate
     */
    public function getPaymentDate()
    {
        return $this->paymentDate;
    }

    /**
     * Sets the value of paymentDate.
     *
     * @param \DateTime $created $paymentDate the paid at
     *
     * @return self
     */
    public function setPaymentDate($paymentDate)
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }
}
