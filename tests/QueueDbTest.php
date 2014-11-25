<?php

use Mockery as m;

class QueueDbTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testPushProperlyPushesJobOntoDb()
	{
		$queue = new Expanse\L4dbqueue\DbQueue($iron = m::mock('DB'), m::mock('Illuminate\Http\Request'), 'default', true);
		$crypt = m::mock('Illuminate\Encryption\Encrypter');
		$queue->setEncrypter($crypt);
		$crypt->shouldReceive('encrypt')->once()->with(json_encode(array('job' => 'foo', 'data' => array(1, 2, 3), 'attempts' => 1, 'queue' => 'default')))->andReturn('encrypted');
		$iron->shouldReceive('postMessage')->once()->with('default', 'encrypted', array())->andReturn((object) array('id' => 1));
		$queue->push('foo', array(1, 2, 3));
	}


	public function testPushProperlyPushesJobOntoDbWithoutEncryption()
	{
		$queue = new Expanse\L4dbqueue\DbQueue($iron = m::mock('DB'), m::mock('Illuminate\Http\Request'), 'default');
		$crypt = m::mock('Illuminate\Encryption\Encrypter');
		$queue->setEncrypter($crypt);
		$crypt->shouldReceive('encrypt')->never();
		$iron->shouldReceive('postMessage')->once()->with('default', json_encode(['job' => 'foo', 'data' => [1, 2, 3], 'attempts' => 1, 'queue' => 'default']), array())->andReturn((object) array('id' => 1));
		$queue->push('foo', array(1, 2, 3));
	}


	public function testPushProperlyPushesJobOntoDbWithClosures()
	{
		$queue = new Expanse\L4dbqueue\DbQueue($iron = m::mock('DB'), m::mock('Illuminate\Http\Request'), 'default', true);
		$crypt = m::mock('Illuminate\Encryption\Encrypter');
		$queue->setEncrypter($crypt);
		$name = 'Foo';
		$closure = new Illuminate\Support\SerializableClosure($innerClosure = function() use ($name) { return $name; });
		$crypt->shouldReceive('encrypt')->once()->with(serialize($closure))->andReturn('serial_closure');
		$crypt->shouldReceive('encrypt')->once()->with(json_encode(array(
			'job' => 'IlluminateQueueClosure', 'data' => array('closure' => 'serial_closure'), 'attempts' => 1, 'queue' => 'default',
		)))->andReturn('encrypted');
		$iron->shouldReceive('postMessage')->once()->with('default', 'encrypted', array())->andReturn((object) array('id' => 1));
		$queue->push($innerClosure);
	}


	public function testDelayedPushProperlyPushesJobOntoDb()
	{
		$queue = new Expanse\L4dbqueue\DbQueue($iron = m::mock('DB'), m::mock('Illuminate\Http\Request'), 'default', true);
		$crypt = m::mock('Illuminate\Encryption\Encrypter');
		$queue->setEncrypter($crypt);
		$crypt->shouldReceive('encrypt')->once()->with(json_encode(array(
			'job' => 'foo', 'data' => array(1, 2, 3), 'attempts' => 1, 'queue' => 'default',
		)))->andReturn('encrypted');
		$iron->shouldReceive('postMessage')->once()->with('default', 'encrypted', array('delay' => 5))->andReturn((object) array('id' => 1));
		$queue->later(5, 'foo', array(1, 2, 3));
	}


	public function testDelayedPushProperlyPushesJobOntoDbWithTimestamp()
	{
		$now = Carbon\Carbon::now();
		$queue = $this->getMock('Expanse\L4dbqueue\DbQueue', array('getTime'), array($iron = m::mock('DB'), m::mock('Illuminate\Http\Request'), 'default', true));
		$crypt = m::mock('Illuminate\Encryption\Encrypter');
		$queue->setEncrypter($crypt);
		$queue->expects($this->once())->method('getTime')->will($this->returnValue($now->getTimestamp()));
		$crypt->shouldReceive('encrypt')->once()->with(json_encode(array('job' => 'foo', 'data' => array(1, 2, 3), 'attempts' => 1, 'queue' => 'default')))->andReturn('encrypted');
		$iron->shouldReceive('postMessage')->once()->with('default', 'encrypted', array('delay' => 5))->andReturn((object) array('id' => 1));
		$queue->later($now->addSeconds(5), 'foo', array(1, 2, 3));
	}


	public function testPopProperlyPopsJobOffOfDb()
	{
		$queue = new Expanse\L4dbqueue\DbQueue($iron = m::mock('DB'), m::mock('Illuminate\Http\Request'), 'default', true);
		$crypt = m::mock('Illuminate\Encryption\Encrypter');
		$queue->setEncrypter($crypt);
		$queue->setContainer(m::mock('Illuminate\Container\Container'));
		$iron->shouldReceive('getMessage')->once()->with('default')->andReturn($job = m::mock('DB_Message'));
		$job->body = 'foo';
		$crypt->shouldReceive('decrypt')->once()->with('foo')->andReturn('foo');
		$result = $queue->pop();

		$this->assertInstanceOf('Expanse\L4dbqueue\Jobs\DbJob', $result);
	}


	public function testPopProperlyPopsJobOffOfDbWithoutEncryption()
	{
		$queue = new Expanse\L4dbqueue\DbQueue($iron = m::mock('DB'), m::mock('Illuminate\Http\Request'), 'default');
		$crypt = m::mock('Illuminate\Encryption\Encrypter');
		$queue->setEncrypter($crypt);
		$queue->setContainer(m::mock('Illuminate\Container\Container'));
		$iron->shouldReceive('getMessage')->once()->with('default')->andReturn($job = m::mock('DB_Message'));
		$job->body = 'foo';
		$crypt->shouldReceive('decrypt')->never();
		$result = $queue->pop();

		$this->assertInstanceOf('Expanse\L4dbqueue\Jobs\DbJob', $result);
	}


	public function testPushedJobsCanBeMarshaled()
	{
		$queue = $this->getMock('Expanse\L4dbqueue\DbQueue', array('createPushedDbJob'), array($iron = m::mock('DB'), $request = m::mock('Illuminate\Http\Request'), 'default', true));
		$crypt = m::mock('Illuminate\Encryption\Encrypter');
		$queue->setEncrypter($crypt);
		$request->shouldReceive('header')->once()->with('iron-message-id')->andReturn('message-id');
		$request->shouldReceive('getContent')->once()->andReturn($content = json_encode(array('foo' => 'bar')));
		$crypt->shouldReceive('decrypt')->once()->with($content)->andReturn($content);
		$job = (object) array('id' => 'message-id', 'body' => json_encode(array('foo' => 'bar')), 'pushed' => true);
		$queue->expects($this->once())->method('createPushedDbJob')->with($this->equalTo($job))->will($this->returnValue($mockDbJob = m::mock('StdClass')));
		$mockDbJob->shouldReceive('fire')->once();

		$response = $queue->marshal();

		$this->assertInstanceOf('Illuminate\Http\Response', $response);
		$this->assertEquals(200, $response->getStatusCode());
	}

}
