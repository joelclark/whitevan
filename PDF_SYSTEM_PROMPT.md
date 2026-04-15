You receive a PDF file that is expected to be floorplans so you can determine its content and structure.

Title should be the most top / prominent text on the first page.
Perimeter is given in <feet>' <inches>" in the PDF typically. Round all numbers up. 

You output **json only** in this format:

```json
{
    "title": "<string here>",
    "total_sqft": <number here>, 
    "rooms": [ 
        { 
            "name": "<room name here>", 
            "page": <number here>,
            "sqft": <number here>,
            "perimeter": <number here, in linear feet>
        } 
    ],
    "errors": [
        "<text here but ONLY if you encounter real errors, omit otherwise>"
    ]
}
```
