<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for the local_ai_coursecreator event classes.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_coursecreator;

/**
 * Tests that each plugin event triggers cleanly and round-trips through the log store.
 *
 * @package   local_ai_coursecreator
 * @covers    \local_ai_coursecreator\event\course_generation_started
 * @covers    \local_ai_coursecreator\event\course_generation_completed
 * @covers    \local_ai_coursecreator\event\course_generation_failed
 * @covers    \local_ai_coursecreator\event\course_backup_downloaded
 * @covers    \local_ai_coursecreator\event\course_restored
 * @covers    \local_ai_coursecreator\event\connection_tested
 */
final class event_test extends \advanced_testcase {
    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Trigger an event, assert it was captured, and check its describable output.
     *
     * @param \core\event\base $event The already-created (not yet triggered) event.
     * @return \core\event\base The captured event instance.
     */
    private function capture(\core\event\base $event): \core\event\base {
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $captured = reset($events);
        $this->assertInstanceOf(get_class($event), $captured);

        // Name resolves to a real string and description builds without throwing.
        $this->assertIsString($captured::get_name());
        $this->assertNotEmpty($captured->get_description());
        $this->assertInstanceOf(\moodle_url::class, $captured->get_url());

        // The stored payload must survive a restore round-trip (log backup/restore path).
        $restored = \core\event\base::restore($captured->get_data(), ['origin' => 'restore']);
        $this->assertInstanceOf(get_class($event), $restored);

        return $captured;
    }

    /**
     * The generation-started event carries request metadata.
     */
    public function test_course_generation_started(): void {
        $this->setAdminUser();
        $event = event\course_generation_started::create([
            'context' => \context_system::instance(),
            'other'   => [
                'inputbytes'    => 4096,
                'includeimages' => true,
                'filecount'     => 2,
                'systemprompt'  => false,
            ],
        ]);
        $captured = $this->capture($event);
        $this->assertSame(4096, $captured->other['inputbytes']);
        $this->assertStringContainsString('4096', $captured->get_description());
    }

    /**
     * The generation-completed event carries the backup metadata.
     */
    public function test_course_generation_completed(): void {
        $this->setAdminUser();
        $event = event\course_generation_completed::create([
            'context' => \context_system::instance(),
            'other'   => [
                'safetitle' => 'Intro_to_Testing',
                'sizebytes' => 20480,
                'filename'  => 'Intro_to_Testing_123.mbz',
            ],
        ]);
        $captured = $this->capture($event);
        $this->assertSame(20480, $captured->other['sizebytes']);
    }

    /**
     * The generation-failed event carries a reason code.
     */
    public function test_course_generation_failed(): void {
        $this->setAdminUser();
        $event = event\course_generation_failed::create([
            'context' => \context_system::instance(),
            'other'   => [
                'reason'  => 'too_large',
                'message' => 'The source content exceeds the limit.',
            ],
        ]);
        $captured = $this->capture($event);
        $this->assertSame('too_large', $captured->other['reason']);
        $this->assertStringContainsString('too_large', $captured->get_description());
    }

    /**
     * The backup-downloaded event carries the file name.
     */
    public function test_course_backup_downloaded(): void {
        $this->setAdminUser();
        $event = event\course_backup_downloaded::create([
            'context' => \context_system::instance(),
            'other'   => [
                'filename'  => 'course_123.mbz',
                'safetitle' => 'course',
            ],
        ]);
        $captured = $this->capture($event);
        $this->assertSame('course_123.mbz', $captured->other['filename']);
    }

    /**
     * The course-restored event is bound to the new course context and id.
     */
    public function test_course_restored(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $event = event\course_restored::create([
            'context'  => \context_course::instance($course->id),
            'objectid' => $course->id,
            'other'    => ['safetitle' => 'Restored Course'],
        ]);
        $captured = $this->capture($event);
        $this->assertSame((int) $course->id, (int) $captured->objectid);
        $this->assertStringContainsString('/course/view.php', $captured->get_url()->out(false));
    }

    /**
     * The connection-tested event carries the diagnostic result.
     */
    public function test_connection_tested(): void {
        $this->setAdminUser();
        $event = event\connection_tested::create([
            'context' => \context_system::instance(),
            'other'   => [
                'result'     => 'REACHABLE',
                'httpcode'   => 200,
                'configured' => true,
            ],
        ]);
        $captured = $this->capture($event);
        $this->assertSame('REACHABLE', $captured->other['result']);
    }

    /**
     * Missing required `other` data must raise a coding exception.
     */
    public function test_missing_other_data_rejected(): void {
        $this->setAdminUser();
        $this->expectException(\coding_exception::class);
        event\course_generation_started::create([
            'context' => \context_system::instance(),
            'other'   => [],
        ])->trigger();
    }
}
