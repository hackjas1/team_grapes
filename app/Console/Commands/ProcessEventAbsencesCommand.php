<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\AbsenceProcessorService;
use Illuminate\Console\Command;

class ProcessEventAbsencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:process-absences {event_id? : The ID of a specific event to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-process absences and generate non-attendance fines for completed or expired events';

    /**
     * Execute the console command.
     */
    public function handle(AbsenceProcessorService $service): int
    {
        $eventId = $this->argument('event_id');

        if ($eventId) {
            $event = Event::find($eventId);
            if (!$event) {
                $this->error("Event with ID {$eventId} not found.");
                return Command::FAILURE;
            }

            $this->info("Processing absences for event: {$event->title} (ID: {$event->id})...");
            $stats = $service->processEventAbsences($event);

            $this->info("Eligible Students: {$stats['eligible_students_count']}");
            $this->info("Attended: {$stats['attendees_count']}");
            $this->info("Absences Generated: {$stats['absent_records_created']}");
            $this->info("Total Fines Generated: PHP " . number_format($stats['total_fines_generated'], 2));

            return Command::SUCCESS;
        }

        $this->info("Checking for expired active events to conclude and process absences...");
        $results = $service->processExpiredEvents();

        if (empty($results)) {
            $this->info("No expired active events found.");
            return Command::SUCCESS;
        }

        $this->info("Processed " . count($results) . " event(s):");
        foreach ($results as $r) {
            $this->line(" - Event #{$r['event_id']} '{$r['event_title']}': {$r['absent_records_created']} absences generated (PHP " . number_format($r['total_fines_generated'], 2) . ")");
        }

        return Command::SUCCESS;
    }
}
