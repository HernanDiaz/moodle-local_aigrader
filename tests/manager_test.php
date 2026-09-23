<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_aigrader;

/**
 * Tests for how the grading manager records which AI provider and model were used.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Call the private manager::resolve_ai_origin().
     *
     * @param int $userid User who made the AI request.
     * @return array Keys 'provider' and 'model'.
     */
    private function resolve(int $userid): array {
        $method = new \ReflectionMethod(manager::class, 'resolve_ai_origin');
        return $method->invoke(null, $userid);
    }

    /**
     * Record a processed generate_text action the way core_ai does.
     *
     * @param int $userid User who made the request.
     * @param string $provider Provider plugin that handled it.
     * @param int $actionid Id in the action's own table (must be unique per call).
     */
    private function register_action(int $userid, string $provider, int $actionid): void {
        global $DB;
        $DB->insert_record('ai_action_register', (object) [
            'actionname'    => 'generate_text',
            'actionid'      => $actionid,
            'success'       => 1,
            'userid'        => $userid,
            'contextid'     => \context_system::instance()->id,
            'provider'      => $provider,
            'timecreated'   => time(),
            'timecompleted' => time(),
        ]);
    }

    /**
     * Without any processed action, nothing is claimed.
     */
    public function test_nothing_known_without_a_processed_action(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertSame(['provider' => null, 'model' => null], $this->resolve((int) $user->id));
    }

    /**
     * The provider comes from Moodle's own action register; the model from
     * that provider's configured default.
     */
    public function test_provider_from_register_and_model_from_provider_config(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_config('action_generate_text_model', 'gpt-4o-mini', 'aiprovider_openai');
        set_config('action_generate_text_model', 'some-azure-model', 'aiprovider_azureai');

        $this->register_action((int) $user->id, 'aiprovider_azureai', 1);
        $this->register_action((int) $user->id, 'aiprovider_openai', 2);

        // The latest action wins.
        $this->assertSame(
            ['provider' => 'aiprovider_openai', 'model' => 'gpt-4o-mini'],
            $this->resolve((int) $user->id)
        );
    }

    /**
     * When the provider has no configured model, the model is left empty.
     */
    public function test_model_left_empty_when_unknown(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_config('action_generate_text_model', '', 'aiprovider_openai');

        $this->register_action((int) $user->id, 'aiprovider_openai', 3);

        $this->assertSame(['provider' => 'aiprovider_openai', 'model' => null], $this->resolve((int) $user->id));
    }
}
