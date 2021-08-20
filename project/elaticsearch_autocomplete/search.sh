{
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
}