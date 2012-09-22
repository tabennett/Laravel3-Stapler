<?php echo '<?php' . PHP_EOL; ?>

class <?php echo $class_name; ?> {

	/**
	 * Make changes to the database.
	 *
	 * @return void
	 */
	public function up()
	{	
		Schema::table('<?php echo $table_name; ?>', function($table) {
		<?php foreach($column_names as $column_name): ?>
		<?php if(isset($is_nullable) && $is_nullable == true): ?>			
			$table->string("<?php echo $column_name; ?>_file_name")->nullable();
			$table->integer("<?php echo $column_name; ?>_file_size")->nullable();
			$table->string("<?php echo $column_name; ?>_content_type")->nullable();
			$table->timestamp("<?php echo $column_name; ?>_uploaded_at")->nullable();
		<?php else: ?>
		
			$table->string("<?php echo $column_name; ?>_file_name");
			$table->integer("<?php echo $column_name; ?>_file_size");
			$table->string("<?php echo $column_name; ?>_content_type");
			$table->timestamp("<?php echo $column_name; ?>_uploaded_at");
		<?php endif; ?>
		<?php endforeach; ?>

		});

	}

	/**
	 * Revert the changes to the database.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('<?php echo $table_name; ?>', function($table) {
		<?php foreach($column_names as $column_name): ?>

			$table->drop_column("<?php echo $column_name; ?>_file_name");
			$table->drop_column("<?php echo $column_name; ?>_file_size");
			$table->drop_column("<?php echo $column_name; ?>_content_type");
			$table->drop_column("<?php echo $column_name; ?>_uploaded_at");
		<?php endforeach; ?>

		});
	}

}