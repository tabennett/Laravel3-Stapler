<?php

use Laravel\CLI\Command;

class Stapler_Fasten_Task {

	/**
	 * The table's data.
	 *
	 * @var array
	 */
	public $data = array();

	/**
	 * Create a new scaffold.
	 *
	 * @param  array  $arguments
	 * @return void
	 */
	public function run($arguments)
	{
		$count = count($arguments);

		// If there are zero arguments, we were given absolutely nothing.
		if($count == 0)
		{
			print 'Missing table name!'.PHP_EOL;
			print $this->usage();
		}
		elseif($count == 1)
		{
			print 'Missing column name!'.PHP_EOL;
			print $this->usage();
		}
		elseif($count > 3)
		{
			print 'Too many arguments supplied!'.PHP_EOL;
			print $this->usage();
		}
		else
		{

			if($count == 3 && $arguments[2] == 'false') $is_nullable = false;
			else $is_nullable = true;
			
			$this->data = [	'table_name' => $arguments[0],
							'column_names' => explode(',', $arguments[1]),
							'is_nullable' => $is_nullable ];

			try {
				# see if this throws an error or not
				DB::table($this->data['table_name'])->first();

				# create the migration file since we found the table in database
				$this->create_migration();

			} catch(Exception $e) {
				print "no such table found in database: " . $this->data['table_name'];
			}

		}
	}


	/**
	 * Create a new migration.
	 *
	 * @return void
	 */
	public function create_migration()
	{
		$prefix = date('Y_m_d_His');

		$path = path('app').'migrations'.DS;

		if ( ! is_dir($path)) mkdir($path);

		$file  = $path . $prefix . '_staple_' . $this->data['table_name'] 
					   . '_table' . EXT;

		$this->data['class_name'] = 'Staple_' . ucfirst($this->data['table_name']) 
											  . '_Table';

		# generate the migration file
		$migration = View::make('stapler::migration', $this->data)->render();

		# save the migration file
		File::put($file, $migration);

		Log::write('info', 'created migration: ' . $file);

		print 'created migration: ' . $file;
	}


	public function usage()
	{
		return "php artisan stapler::fasten <table_name> <column_name> <nullable = true>" . PHP_EOL;
	}
}