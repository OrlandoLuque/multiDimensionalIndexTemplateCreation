local key = ARGV[1]
return redis.call("setex", key, 50, 'working')