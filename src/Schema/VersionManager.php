<?php

    namespace MulerTech\Database\Schema;

    use MulerTech\Database\Entity\Version;
    use MulerTech\Database\ORM\EntityManagerInterface;

    class VersionManager
    {
        private EntityManagerInterface $entityManager;

        /**
         * @param EntityManagerInterface $entityManager
         */
        public function __construct(EntityManagerInterface $entityManager)
        {
            $this->entityManager = $entityManager;
        }

        /**
         * Récupère la version actuelle de la base de données
         *
         * @return string|null
         */
        public function getCurrentVersion(): ?string
        {
            $version = $this->entityManager->find(Version::class, 1);
            return $version ? $version->getVersion() : null;
        }

        /**
         * Met à jour la version de la base de données
         *
         * @param string $newVersion
         * @return void
         */
        public function updateVersion(string $newVersion): void
        {
            $version = $this->entityManager->find(Version::class, 1);

            if ($version === null) {
                $version = new Version();
                $version->setId(1);
                $this->entityManager->persist($version);
            }

            $version->setVersion($newVersion);
            $version->setDate_version(date('Y-m-d H:i:s'));
            $this->entityManager->flush();
        }

        /**
         * Vérifie si une mise à jour de version est nécessaire
         *
         * @param string $targetVersion
         * @return bool
         */
        public function isUpdateNeeded(string $targetVersion): bool
        {
            $currentVersion = $this->getCurrentVersion();

            if ($currentVersion === null) {
                return true;
            }

            return (float)$currentVersion < (float)$targetVersion;
        }
    }