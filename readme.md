Run containers:
>> cd laradock && ./sync.sh up workspace nginx php-fpm mysql memcached && cd ..

Write stress test

> siege -d1 -c10   -t15s -f urls.txt 2> siege-result/write-d1-c10-t15s-result.txt
> siege -d1 -c25   -t15s -f urls.txt 2> siege-result/write-d1-c25-t15s-result.txt
> siege -d1 -c50   -t15s -f urls.txt 2> siege-result/write-d1-c50-t15s-result.txt
> siege -d1 -c100  -t15s -f urls.txt 2> siege-result/write-d1-c100-t15s-result.txt



Read stress test:
> siege -c250 -d1 -t130s  -f cache_urls.txt -H "X-Probabilistic-Cache-Flushing: 0"  2> siege-result/read-without-cache-flush-c250-d1-t130-result.txt
> siege -c250 -d1 -t130s  -f cache_urls.txt -H "X-Probabilistic-Cache-Flushing: 1"  2> siege-result/read-with-cache-flush-c250-d1-t130-result.txt
