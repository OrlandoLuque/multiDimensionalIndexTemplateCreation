local keys = {ARGV[1], ARGV[2], ARGV[3], ARGV[4], ARGV[5], ARGV[6], ARGV[7], ARGV[8]}
--local keys = subrange(ARGV, 1,8)
local foundKeys = redis.call("mget", unpack(keys))
local matrixMethodsIndex = {
    ARGV[9],  ARGV[10],  ARGV[11],  ARGV[12],  ARGV[13],  ARGV[14],  ARGV[15],  ARGV[16]}
local templateCountKey = ARGV[17]
local templateListKey = ARGV[18]
local LastTemplateKey = ARGV[19]
local generationSetString = ARGV[20]
local generationSetKey = ARGV[21]


--------
local found = nil
local templateId = nil
local templateCount = false
local foundIndex = false
local r1 = 0
local r2 = 0
local r3 = 0
local r4 = 0

for i = 1, table.getn(foundKeys) do
    if foundKeys[i] ~= false then
        found = i
        foundIndex = i
        templateId = foundKeys[i]
        break
    end
end

if found == nil then
    templateId = redis.call("incr", templateCountKey)
    templateCount = templateId
    r1 = redis.call("set", keys[1], templateId)
    r2 = redis.call("rPush", templateListKey, keys[1])
    found = 1;
else
    --templateCount = redis.call("get", templateCountKey)
end

local generationProcessData = generationSetString
    .. '->' .. matrixMethodsIndex[found]
        .. '->' .. templateId;
r3 = redis.call("rPush", generationSetKey, generationProcessData)

-- to continue later if the process stops
r4 = redis.call("set", LastTemplateKey, generationSetString)

return {generationProcessData, foundIndex, templateCount, r1, r2, r3, r4}

--[[

return {ARGV[1], ARGV[2], ARGV[3], ARGV[4]
, ARGV[5], ARGV[6], ARGV[7], ARGV[8]}

return {foundKeys[1], foundKeys[2], foundKeys[3], foundKeys[4]
, foundKeys[5], foundKeys[6], foundKeys[7], foundKeys[8]}

return {matrixMethodsIndex[1], matrixMethodsIndex[2], matrixMethodsIndex[3], matrixMethodsIndex[4]
, matrixMethodsIndex[5], matrixMethodsIndex[6], matrixMethodsIndex[7], matrixMethodsIndex[8]}

--]]
