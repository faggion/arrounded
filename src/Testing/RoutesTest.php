<?php
namespace Arrounded\Testing;

use Artisan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RoutesTest extends \TestCase
{
	/**
	 * The routes to ignore
	 *
	 * @var array
	 */
	protected $ignored = array(
		'_debugbar/open',
		'_profiler',
		'logout',
	);

	/**
	 * A list of URLs that redirect back
	 *
	 * @var array
	 */
	protected $redirectBack = array();

	/**
	 * The additional routes
	 *
	 * @var array
	 */
	protected $additional = array();

	/**
	 * Recreate the database before each test
	 *
	 * @return void
	 */
	public function setUp()
	{
		parent::setUp();

		$this->recreateDatabase();
	}

	/**
	 * Seed the current database
	 *
	 * @return void
	 */
	protected function seedDatabase()
	{
		Artisan::call('db:seed');
	}

	/**
	 * Provide the routes to test
	 *
	 * @return array
	 */
	public function provideRoutes()
	{
		// Set up database
		$this->setUp();

		// Get the routes to call
		$crawler = new Crawler($this->app);
		$crawler->setIgnored($this->ignored);
		$crawler->setNamespace($this->namespaces['models']);

		return $crawler->provideRoutes($this->additional);
	}

	/**
	 * @dataProvider provideRoutes
	 */
	public function testCanAccessRoutes($route)
	{
		// Authentify user
		$this->authentify();

		// Spoof redirect back
		$redirectsBack = $this->redirectsBack($route);
		if ($redirectsBack) {
			$this->spoofRedirectBack();
		}

		// Call route and catch common errors
		try {
			$this->call('GET', $route);
		} catch (InvalidArgumentException $e) {
			$this->fail($e->getMessage());
		} catch (ModelNotFoundException $e) {
			$this->fail($e->getMessage());
		} catch (NotFoundHttpException $e) {
			$this->fail($e->getMessage());
		}

		// Assert status if the route was called correctly
		if ($redirectsBack) {
			$this->assertRedirectedTo('/');
		} else {
			$this->assertResponseOk();
		}
	}

	/**
	 * Checks if an URL redirects back
	 *
	 * @param string $route
	 *
	 * @return boolean
	 */
	protected function redirectsBack($route)
	{
		$route   = str_replace($this->app['url']->to('/').'/', null, $route);
		$pattern = implode('$|^', $this->redirectBack);
		$pattern = '#(^' .$pattern. '$)#';

		return preg_match($pattern, $route);
	}
}
