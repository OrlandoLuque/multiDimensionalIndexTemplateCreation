local key = ARGV[1]
return redis.call("set", key, "completed")