<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journalise chaque tentative d'import et repare le typage de invoice.
 *
 * Squash des trois migrations d'ingestion : la table import_attempt arrive
 * directement dans sa forme finale (4 etats, pas de source_hash), donc il n'y
 * a plus d'UPDATE de donnees ni d'index recree deux fois.
 */
final class Version20260720120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journalise chaque tentative d\'import et repare le typage de invoice';
    }

    public function up(Schema $schema): void
    {
        // Sequence separee, et non SERIAL : c'est la strategie d'ID que Doctrine
        // genere pour ce projet (cf. invoice_id_seq). Melanger les deux ferait
        // diverger doctrine:schema:validate.
        $this->addSql('CREATE SEQUENCE import_attempt_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE import_attempt (
                id            INT           NOT NULL,
                client        VARCHAR(64)   NOT NULL,
                source_file   VARCHAR(1024) NOT NULL,
                attempted_at  TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                status        VARCHAR(16)   NOT NULL,
                invoice_count INT           NOT NULL,
                errors        JSONB         NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        // Pas de DEFAULT '[]' en base : le mapping ORM ne declare aucun
        // defaut pour errors (le defaut vit cote PHP, `$errors = []`), et
        // doctrine:schema:validate exige que les deux s'accordent.
        // Le commentaire DC2Type est la marque que Doctrine pose lui-meme
        // pour reconnaitre ses types custom (datetimetz_immutable,
        // date_immutable) qui n'ont pas de type SQL natif distinct ;
        // ecrite ici parce que la migration est en SQL brut et ne passe pas
        // par SchemaTool, qui l'ajouterait automatiquement.
        $this->addSql("COMMENT ON COLUMN import_attempt.attempted_at IS '(DC2Type:datetimetz_immutable)'");

        // Index partiel NON-UNIQUE sur les tentatives actives : c'est lui que
        // canAttempt() lit. Le filtre est porteur — sans lui, un fichier
        // rejete puis resoumis serait declare « inchange » et ne serait jamais
        // importe. Declare aussi en #[ORM\Index] sur ImportAttemptEntity pour
        // que schema:validate le reconnaisse au lieu de le croire orphelin.
        //
        // Non-UNIQUE : un UNIQUE partiel n'est pas representable via
        // #[ORM\UniqueConstraint], et schema:validate le signalerait comme
        // divergent dans dev ET test. Il ne ferme donc pas la fenetre TOCTOU
        // de canAttempt() — voir le docblock d'ImportAttemptRepository.
        $this->addSql(<<<'SQL'
            CREATE INDEX uniq_attempt_active
            ON import_attempt (client, source_file)
            WHERE status IN ('pending', 'done')
            SQL);

        // Un seul ALTER, aucune migration de donnees : cette migration exige
        // une table invoice VIDE. ADD COLUMN issued_at DATE NOT NULL echouera
        // bruyamment sur une table peuplee — c'est voulu, pas un oubli : il
        // n'existe aucune valeur de backfill honnete pour la date d'emission
        // d'une facture preexistante. Si la table contient une ligne, videz-la
        // (ou ecrivez une vraie migration de donnees) avant de rejouer ceci.
        $this->addSql('ALTER TABLE invoice ALTER COLUMN amount TYPE NUMERIC(19, 4)');
        $this->addSql('ALTER TABLE invoice ADD COLUMN issued_at DATE NOT NULL');
        $this->addSql("COMMENT ON COLUMN invoice.issued_at IS '(DC2Type:date_immutable)'");
        $this->addSql('ALTER TABLE invoice ADD COLUMN attempt_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT fk_invoice_attempt FOREIGN KEY (attempt_id) REFERENCES import_attempt (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_invoice_attempt ON invoice (attempt_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT fk_invoice_attempt');
        $this->addSql('DROP INDEX idx_invoice_attempt');
        $this->addSql('ALTER TABLE invoice DROP COLUMN attempt_id');
        $this->addSql('ALTER TABLE invoice DROP COLUMN issued_at');
        $this->addSql('ALTER TABLE invoice ALTER COLUMN amount TYPE DOUBLE PRECISION');
        $this->addSql('DROP TABLE import_attempt');
        $this->addSql('DROP SEQUENCE import_attempt_id_seq');
    }
}
