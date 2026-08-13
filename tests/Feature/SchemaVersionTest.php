<?php

namespace Tests\Feature;

use App\Actions\Results\AssembleResultsDocument;
use Tests\TestCase;

/**
 * The schema version exists in two languages: the app stamps it onto every run
 * it produces, and the community validator decides from it whether a run may
 * join the gallery. Nothing links them but a comment, and getting them out of
 * step fails in the worst available way — the app keeps minting runs that the
 * bot rejects, on a repository the submitter cannot see the validator for.
 *
 * So the comment is checked rather than trusted.
 */
class SchemaVersionTest extends TestCase
{
    protected const VALIDATOR = 'docs/shared/submission/validate.mjs';

    public function test_the_app_and_the_community_validator_agree_on_the_schema_version(): void
    {
        $this->assertSame(
            AssembleResultsDocument::SCHEMA_VERSION,
            $this->validatorSchemaVersion(),
            'AssembleResultsDocument::SCHEMA_VERSION and SCHEMA_VERSION in '.self::VALIDATOR.' have drifted. '.
            'Bump both, and add the superseded version to SUPERSEDED_SCHEMAS with the reason its numbers cannot be compared.'
        );
    }

    /**
     * Every version below the current one has to carry a reason, because that
     * string is what a submitter is shown when their run is turned away.
     */
    public function test_every_superseded_schema_version_explains_why_it_was_superseded(): void
    {
        $source = $this->validatorSource();

        preg_match('/const SUPERSEDED_SCHEMAS = \{(.*?)\n\}/s', $source, $matches);

        $this->assertNotEmpty($matches, 'Could not find SUPERSEDED_SCHEMAS in '.self::VALIDATOR.'.');

        for ($version = 1; $version < AssembleResultsDocument::SCHEMA_VERSION; $version++) {
            $this->assertMatchesRegularExpression(
                "/(^|\s){$version}: '[^']{20,}'/m",
                $matches[1],
                "Schema version {$version} is superseded but SUPERSEDED_SCHEMAS does not say why, so a submitter running that build is rejected without being told what changed."
            );
        }
    }

    protected function validatorSchemaVersion(): int
    {
        preg_match('/export const SCHEMA_VERSION = (\d+)/', $this->validatorSource(), $matches);

        $this->assertNotEmpty($matches, 'Could not find an exported SCHEMA_VERSION in '.self::VALIDATOR.'.');

        return (int) $matches[1];
    }

    protected function validatorSource(): string
    {
        $path = base_path(self::VALIDATOR);

        $this->assertFileExists($path, 'The community validator is missing; the app can still mint runs that nothing will accept.');

        return (string) file_get_contents($path);
    }
}
