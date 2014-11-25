<?php namespace Expanse\L4dbqueue;

use Illuminate\Support\ServiceProvider;

class L4dbqueueServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('Expanse/L4dbqueue');
        $manager = $this->app['queue'];
		$manager->addConnector('mysql', function()
		{
		    return new Queue\Connectors\MysqlConnector;
		});
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
