(function(){
  document.addEventListener('DOMContentLoaded', () => {
    // Find all charts-paragraphs on a page.
    const paragraphs = document.querySelectorAll('.paragraph--type--chartjs-chart');

    // Create canvas elements for each chart and render using the json-data.
    paragraphs.forEach((element) => {
      element.firstElementChild.remove();

      const json = JSON.parse(element.textContent);

      const canvas = document.createElement('canvas')
      canvas.id = json.type
      element.append(canvas);

      new Chart(document.getElementById(json.type), json);
    });
  })
})()

/*
EXAMPLES:
How it works:
1, Add a chartjs_chart paragraph to a page
2. Copy one of the definitions from below to the json -text field.
3. Save the page
The script reads the "type" from the json data and uses it as an ID for the canvas element.
Then a new Chart is then rendered to the canvas element (by ID).

bar:
{"type":"bar","data":{"labels":["A","B","C"],"datasets":[{"data":[10,20,15]}]}}
line:
{"type":"line","data":{"labels":["Jan","Feb","Mar"],"datasets":[{"data":[10,20,15]}]}}
Pie:
{"type":"pie","data":{"labels":["A","B","C"],"datasets":[{"data":[30,50,20]}]}}
Doughnut:
{"type":"doughnut","data":{"labels":["A","B","C"],"datasets":[{"data":[30,50,20]}]}}
Radar:
{"type":"radar","data":{"labels":["A","B","C"],"datasets":[{"data":[10,20,15]}]}}
*/
