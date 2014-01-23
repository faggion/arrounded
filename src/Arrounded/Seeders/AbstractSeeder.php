<?php
namespace Arrounded\Seeders;

use DB;
use Closure;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * An enhanced core seeder class
 */
abstract class AbstractSeeder extends Seeder
{
	/**
	 * The Faker instance
	 *
	 * @var Faker
	 */
	protected $faker;

	/**
	 * The namespace where the models are
	 *
	 * @var string
	 */
	protected $models;

	/**
	 * Build a new Seed
	 */
	public function __construct()
	{
		// Bind Faker instance if available
		if (class_exists('Faker\Factory')) {
			$this->faker = Faker::create();
		}
	}

	/**
	 * Run a seeder
	 *
	 * @param  string $table
	 *
	 * @return void
	 */
	public function seed($table)
	{
		$timer = microtime(true);
		$this->command->info('Seeding '.$table);
		$this->call($table.'TableSeeder');

		// Log results
		$results = Str::singular($table);
		if (class_exists($results)) {
			$timer   = round(microtime(true) - $timer, 2);
			$this->command->comment(sprintf('-- %s entries created (%ss)', $results::count(), $timer));
		}
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// SEEDERS ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Loop over the specified models
	 *
	 * @param string|array  $models
	 * @param Closure       $closure
	 * @param integer       $min
	 * @param integer       $max
	 *
	 * @return void
	 */
	protected function forModels($models, Closure $closure, $min = 1, $max = null)
	{
		$max = $max ?: $min;

		$models = (array) $models;
		foreach ($models as $model) {
			$model   = $this->models.$model;
			$entries = $model::lists('id');
			foreach ($entries as $entry) {
				$this->times(function() use ($closure, $entry, $model) {
					$closure($entry, $model);
				}, $min, $max);
			}
		}
	}

	/**
	 * Generate X entries
	 *
	 * @param  Closure $closure
	 * @param integer  $min
	 * @param integer  $max
	 *
	 * @return void
	 */
	protected function generateEntries(Closure $closure, $min = 5, $max = null)
	{
		$isTesting = app()->environment('testing');

		// Execute the Closure n times
		$entries = array();
		$this->times(function($i) use ($closure, &$entries) {
			if (!$isTesting) print '.';
			if ($entry = $closure($i)) {
				$entry = $entry->getAttributes();
				$entries[] = $entry;
			}
		}, $min, $max);
		if (!$isTesting) print PHP_EOL;

		if (!empty($entries)) {
			$model = get_called_class();
			$model = str_replace('TableSeeder', null, $model);
			$model = Str::singular($model);

			$model::insert($entries);
		}
	}

	/**
	 * Generate pivot relationships
	 *
	 * @return void
	 */
	protected function generatePivotRelations($model, $modelTwo)
	{
		$foreign    = strtolower($model).'_id';
		$foreignTwo = strtolower($modelTwo).'_id';
		$table      = strtolower($model).'_'.strtolower($modelTwo);

		$number = $this->models.$modelTwo;
		$number = $number::count() * 5;

		for ($i = 0; $i <= $number; $i++) {
			$attributes = array(
				$foreign    => $this->randomModel($model),
				$foreignTwo => $this->randomModel($modelTwo),
			);

			DB::table($table)->insert($attributes);
		}
	}

	/**
	 * Return an array of random models IDs
	 *
	 * @param string $model
	 *
	 * @return array
	 */
	protected function randomModels($model, $min = 5, $max = null)
	{
		// Get a random number of elements
		$model     = $this->models.$model;
		$available = $model::lists('id');
		$number    = $this->faker->randomNumber($min, $max);

		$this->times(function() use ($available, &$entries) {
			$entries[] = $this->faker->randomElement($available);
		}, $min, $max);

		return $entries;
	}

	/**
	 * Get a random model from the database
	 *
	 * @param  string $model
	 *
	 * @return Eloquent
	 */
	protected function randomModel($model, $notIn = array())
	{
		$model  = $this->models.$model;
		$models = $model::query();
		if ($notIn) {
			$models = $models->whereNotIn('id', $notIn);
		}

		return $this->faker->randomElement($models->lists('id'));
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// HELPERS ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Execute an action from $min to $max times
	 *
	 * @param integer  $min
	 * @param integer  $max
	 * @param Closure  $closure
	 *
	 * @return void
	 */
	protected function times(Closure $closure, $min, $max = null)
	{
		// Define the number of times to loop over
		$max   = $max ?: $min + 5;
		$times = $this->faker->randomNumber($min, $max);

		for ($i = 0; $i <= $times; $i++) {
			$closure($i);
		}
	}
}
