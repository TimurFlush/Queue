# [1.0.1](https://github.com/TimurFlush/Queue/releases/tag/v1.0.1) (2018-07-22)
 - BUG FIX: Fixed a bug with deleting a task after the maximum number of failure attempts that has Auto Push in worker.php.
 - Fixed names of branches in the readme.md.
 - BUG FIX: Now TimurFlush\Queue\Adapter\Beanstalk::getTotalJobsInQueue(string $queueName) returns 0 if $queueName branch is not found instead of an exception.

# [1.0.0](https://github.com/TimurFlush/Queue/releases/tag/v1.0.0) (2018-07-22)
 - Initial commit.

