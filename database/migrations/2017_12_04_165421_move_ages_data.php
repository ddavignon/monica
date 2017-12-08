<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class MoveAgesData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contacts = DB::table('contacts')->select('account_id', 'id', 'is_birthdate_approximate', 'birthdate', 'birthday_reminder_id', 'first_met', 'deceased_date')->get();

        foreach ($contacts as $contact) {
            $specialDateDeceasedDateId = null;
            $specialDateBirthdateId = null;
            $specialDateFirstMetDateId = null;

            if ($contact->deceased_date) {
                $specialDateDeceasedDateId = DB::table('special_dates')->insertGetId([
                    'account_id' => $contact->account_id,
                    'contact_id' => $contact->id,
                    'is_age_based' => false,
                    'date' => $contact->deceased_date,
                    'reminder_id' => null,
                    'created_at' => \Carbon\Carbon::now(),
                ]);
            }

            $isBirthdayApproximate = $contact->is_birthdate_approximate;

            if ($contact->birthdate) {
                switch ($isBirthdayApproximate) {
                    case 'unknown':
                        break;
                    case 'approximate':
                        $specialDateBirthdateId = DB::table('special_dates')->insertGetId([
                            'account_id' => $contact->account_id,
                            'contact_id' => $contact->id,
                            'is_age_based' => true,
                            'date' => $contact->birthdate,
                            'reminder_id' => $contact->birthday_reminder_id,
                            'created_at' => \Carbon\Carbon::now(),
                        ]);

                        break;
                    case 'exact':
                        $specialDateBirthdateId = DB::table('special_dates')->insertGetId([
                            'account_id' => $contact->account_id,
                            'contact_id' => $contact->id,
                            'is_age_based' => false,
                            'date' => $contact->birthdate,
                            'reminder_id' => $contact->birthday_reminder_id,
                            'created_at' => \Carbon\Carbon::now(),
                        ]);

                        break;
                }
            }

            if ($contact->first_met) {
               $specialDateFirstMetDateId = DB::table('special_dates')->insertGetId([
                    'account_id' => $contact->account_id,
                    'contact_id' => $contact->id,
                    'is_age_based' => false,
                    'date' => $contact->first_met,
                    'reminder_id' => null,
                    'created_at' => \Carbon\Carbon::now(),
                ]);
            }

            if ($contact->birthdate && $specialDateBirthdateId) {
                DB::table('reminders')
                            ->where('id', $contact->birthday_reminder_id)
                            ->update(['special_date_id' => $specialDateBirthdateId]);
            }

            DB::table('contacts')
                    ->where('id', $contact->id)
                    ->update([
                        'deceased_special_date_id' => $specialDateDeceasedDateId,
                        'birthday_special_date_id' => $specialDateBirthdateId,
                        'first_met_special_date_id' => $specialDateFirstMetDateId,
                    ]);
        }

        Schema::table('contacts', function ($table) {
            $table->dropColumn([
                'deceased_date',
                'first_met',
                'birthdate',
                'is_birthdate_approximate',
                'birthday_reminder_id',
            ]);
        });

        Schema::table('reminders', function ($table) {
            $table->dropColumn([
                'is_birthday',
            ]);
        });
    }
}
