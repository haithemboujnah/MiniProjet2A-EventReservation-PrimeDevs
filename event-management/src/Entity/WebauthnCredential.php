<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\TrustPathLoader;

#[ORM\Entity]
#[ORM\Table(name: 'webauthn_credentials')]
#[ORM\Index(name: 'credential_id_idx', columns: ['credential_id'])]
class WebauthnCredential
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'webauthnCredentials')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $credentialId;

    #[ORM\Column(type: 'binary', length: 255)]
    private $publicKeyCredentialId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $type;

    #[ORM\Column(type: 'text')]
    private string $transports;

    #[ORM\Column(type: 'string', length: 255)]
    private string $attestationType;

    #[ORM\Column(type: 'json')]
    private array $trustPath;

    #[ORM\Column(type: 'binary', length: 255)]
    private $aaguid;

    #[ORM\Column(type: 'string', length: 255)]
    private string $credentialPublicKey;

    #[ORM\Column(type: 'bigint')]
    private int $counter;

    #[ORM\Column(type: 'json')]
    private array $otherUi;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    public function setCredentialId(string $credentialId): self
    {
        $this->credentialId = $credentialId;
        return $this;
    }

    public function getPublicKeyCredentialId()
    {
        return $this->publicKeyCredentialId;
    }

    public function setPublicKeyCredentialId($publicKeyCredentialId): self
    {
        $this->publicKeyCredentialId = $publicKeyCredentialId;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTransports(): string
    {
        return $this->transports;
    }

    public function setTransports(string $transports): self
    {
        $this->transports = $transports;
        return $this;
    }

    public function getAttestationType(): string
    {
        return $this->attestationType;
    }

    public function setAttestationType(string $attestationType): self
    {
        $this->attestationType = $attestationType;
        return $this;
    }

    public function getTrustPath(): array
    {
        return $this->trustPath;
    }

    public function setTrustPath(array $trustPath): self
    {
        $this->trustPath = $trustPath;
        return $this;
    }

    public function getAaguid()
    {
        return $this->aaguid;
    }

    public function setAaguid($aaguid): self
    {
        $this->aaguid = $aaguid;
        return $this;
    }

    public function getCredentialPublicKey(): string
    {
        return $this->credentialPublicKey;
    }

    public function setCredentialPublicKey(string $credentialPublicKey): self
    {
        $this->credentialPublicKey = $credentialPublicKey;
        return $this;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function setCounter(int $counter): self
    {
        $this->counter = $counter;
        return $this;
    }

    public function getOtherUi(): array
    {
        return $this->otherUi;
    }

    public function setOtherUi(array $otherUi): self
    {
        $this->otherUi = $otherUi;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function touch(): self
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }

    public function toPublicKeyCredentialSource(): PublicKeyCredentialSource
    {
        // Handle transports properly - ensure it's an array
        $transports = [];
        if (!empty($this->transports)) {
            $decoded = json_decode($this->transports, true);
            $transports = is_array($decoded) ? $decoded : [];
        }

        return new PublicKeyCredentialSource(
            $this->credentialId,
            $this->type,
            $transports,
            $this->attestationType,
            TrustPathLoader::loadTrustPath($this->trustPath),
            $this->aaguid,
            $this->credentialPublicKey,
            $this->user->getId(),
            $this->counter,
            $this->otherUi
        );
    }

    public static function fromPublicKeyCredentialSource(PublicKeyCredentialSource $source, User $user): self
    {
        $credential = new self();
        $credential->setUser($user);
        $credential->setCredentialId($source->getPublicKeyCredentialId());
        $credential->setPublicKeyCredentialId($source->getPublicKeyCredentialId());
        $credential->setType($source->getType());
        $credential->setTransports(json_encode($source->getTransports()));
        $credential->setAttestationType($source->getAttestationType());
        $credential->setTrustPath($source->getTrustPath()->jsonSerialize());
        $credential->setAaguid($source->getAaguid());
        $credential->setCredentialPublicKey($source->getCredentialPublicKey());
        $credential->setCounter($source->getCounter());
        $credential->setOtherUi($source->getOtherUI());

        return $credential;
    }
}