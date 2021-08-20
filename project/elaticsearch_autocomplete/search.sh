curl -H 'Content-Type: application/json' -XPOST http://localhost:9200/jobs/_search  -d '{
  "suggest": {
    "job-suggest": {
      "prefix": "tiyita",
      "completion": {
        "field": "suggest",
        "size" : 10,
        "fuzzy" : {
            "fuzziness" : 2
        }
      }
    }
  }
}'
