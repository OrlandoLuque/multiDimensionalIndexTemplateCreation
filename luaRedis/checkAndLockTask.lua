local key = ARGV[1]

local r1 = redis.call("get", key)

if r1 == false then
    return redis.call("setex", key, 50, 'working')
end

return false