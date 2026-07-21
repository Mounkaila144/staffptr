<?php

namespace Tests\Unit;

use App\Enums\DocumentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentTypeTest extends TestCase
{
    /** @return array<string, array{DocumentType, string, string}> */
    public static function documentTypes(): array
    {
        return [
            'contrat' => [DocumentType::Contrat, 'contrat', 'Contrat'],
            'convention' => [DocumentType::Convention, 'convention', 'Convention'],
            'fiche de poste' => [DocumentType::FichePoste, 'fiche_poste', 'Fiche de poste'],
            'engagement signé' => [DocumentType::EngagementSigne, 'engagement_signe', 'Engagement signé'],
        ];
    }

    #[DataProvider('documentTypes')]
    public function test_ac_1_document_type_exposes_stable_values_and_french_labels(
        DocumentType $type,
        string $value,
        string $label,
    ): void {
        $this->assertSame($value, $type->value);
        $this->assertSame($label, $type->label());
        $this->assertContains($value, DocumentType::values());
    }
}
