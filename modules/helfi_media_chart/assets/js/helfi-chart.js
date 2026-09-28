(function(){
  document.addEventListener('DOMContentLoaded', () => {
    // Find all charts-paragraphs on a page.
    const paragraphs = document.querySelectorAll('.paragraph--type--chartjs-chart');

    const types = [];

    // Create canvas elements for each chart and render using the json-data.
    paragraphs.forEach((element) => {
      element.firstElementChild.remove();

      const json = JSON.parse(element.textContent);

      const canvas = document.createElement('canvas')
      // Unique id for each canvas.
      const id = json.type + "_" + getIdForType(json.type, types);
      canvas.id = id
      element.append(canvas);

      new Chart(document.getElementById(id), json);
    });
  })

  /**
   * Generate unique id for each chart.
   */
  const getIdForType = (type, typeArray) => {
    if (type in typeArray) {
      typeArray[type] += 1;
    }
    else {
      typeArray[type] = 1;
    }
    return typeArray[type]
  }
})()

/*
EXAMPLES:
How it works:
1, Add a chartjs_chart paragraph to a page
2. Copy one of the definitions from below to the json -text field.
3. Save the page
The script reads the "type" from the json data and uses it as an ID for the canvas element.
Then a new Chart is then rendered to the canvas element (by ID).

bar (vertical):
  {"type":"bar","data":{"labels":["A","B","C"],"datasets":[{"data":[10,20,15]}]}}
  {"type":"bar","data":{"labels":["1900","1910","1920","1930","1940","1950","1960","1970","1980","1990","2000","2010","2020"],"datasets":[{"label":"Helsinki population","data":[79126,118736,152200,205833,252484,368519,448315,523677,483675,492400,555474,588549,656920],"backgroundColor":"#0050A4","borderColor":"#003B7A","borderWidth":1}]},"options":{"responsive":true,"plugins":{"legend":{"display":false},"title":{"display":true,"text":"Helsinki Population by Decade"}},"scales":{"x":{"title":{"display":true,"text":"Year"},"grid":{"display":false}},"y":{"min":0,"max":700000,"title":{"display":true,"text":"Population"},"ticks":{"stepSize":100000},"grid":{"display":true,"color":"#D9D9D9","lineWidth":1}}}}}
  https://www.chartjs.org/docs/latest/samples/bar/vertical.html

bar (horizontal)
  {"type":"bar","data":{"labels":["Helsinki","Espoo","Vantaa"],"datasets":[{"label":"< 18","data":[116897,60147,43800],"backgroundColor":"#005EB8","borderColor":"#005EB8","borderWidth":1},{"label":"18–64","data":[454711,206019,162100],"backgroundColor":"#B3B4B5","borderColor":"#B3B4B5","borderWidth":1},{"label":"65+","data":[122784,54765,45200],"backgroundColor":"#008A9A","borderColor":"#008A9A","borderWidth":1}]},"options":{"indexAxis":"y","responsive":true,"plugins":{"legend":{"display":true},"title":{"display":true,"text":"Population by Age Group, 2024"}},"scales":{"x":{"min":0,"max":750000,"title":{"display":true,"text":"Population"},"ticks":{"stepSize":100000},"grid":{"display":true,"color":"#D9D9D9","lineWidth":1}},"y":{"title":{"display":true,"text":"City"},"grid":{"display":false}}}}}


line:
  {"type":"line","data":{"labels":["Jan","Feb","Mar"],"datasets":[{"data":[10,20,15]}]}}
  {"type":"line","data":{"labels":["2026 Q1","2026 Q2","2026 Q3","2026 Q4","2027 Q1","2027 Q2","2027 Q3","2027 Q4","2028 Q1","2028 Q2","2028 Q3","2028 Q4"],"datasets":[{"label":"Helsinki","data":[693850,696120,698740,702130,704310,706580,708920,711117,713420,715690,717850,720034],"pointStyle":"rect","borderColor":"rgb(75, 192, 192)","backgroundColor":"rgba(75, 192, 192, 0.2)","borderWidth":10,"tension":0.3,"fill":false},{"label":"Espoo","data":[325716,328140,330680,333120,335439,337520,339870,341939,343960,345520,347080,348439],"borderColor":"rgb(54, 162, 235)","backgroundColor":"rgba(54, 162, 235, 0.2)","borderWidth":5,"tension":0.3,"fill":false},{"label":"Vantaa","data":[253900,254980,256120,257199,258160,259080,260120,260955,262020,263080,264170,265205],"pointStyle":"triangle","borderColor":"rgb(255, 99, 132)","backgroundColor":"rgba(255, 99, 132, 0.2)","borderWidth":3,"tension":0.3,"fill":false}]},"options":{"responsive":true,"plugins":{"legend":{"display":true},"title":{"display":true,"text":"Population by City"}},"scales":{"x":{"title":{"display":true,"text":"Period"},"grid":{"display":false}},"y":{"min":0,"max":800000,"title":{"display":true,"text":"Population"},"ticks":{"stepSize":100000},"grid":{"display":true,"color":"#cccccc","lineWidth":1}}}}}
  https://www.chartjs.org/docs/latest/samples/line/line.html

Pie:
  {"type":"pie","data":{"labels":["A","B","C"],"datasets":[{"data":[30,50,20]}]}}
  {"type":"pie","data":{"labels":["Kantakaupunki","Itä-Helsinki","Länsi-Helsinki","Pohjois-Helsinki"],"datasets":[{"label":"Population share","data":[33,27,19,22],"backgroundColor":["#005EB8","#E4003B","#00A88F","#F5A623"],"borderColor":"#FFFFFF","borderWidth":2}]},"options":{"responsive":true,"plugins":{"legend":{"display":true,"position":"right"},"title":{"display":true,"text":"Helsinki Population by Area"}}}}
  {"type":"pie","data":{"labels":["Kantakaupunki","Itä-Helsinki","Länsi-Helsinki","Pohjois-Helsinki"],"datasets":[{"label":"Population share","data":[33,27,19,22],"backgroundColor":["#005EB8","#E4003B","#00A88F","#F5A623"],"borderColor":"#FFFFFF","borderWidth":2}]},"options":{"responsive":true,"plugins":{"legend":{"display":true,"position":"right"},"title":{"display":true,"position":"top","text":"Helsinki Population by Area"}}}}
  {"type":"doughnut","data":{"labels":["Kantakaupunki","Itä-Helsinki","Länsi-Helsinki","Pohjois-Helsinki"],"datasets":[{"label":"Population share","data":[33,27,19,22],"backgroundColor":["#005EB8","#E4003B","#00A88F","#F5A623"],"borderColor":"#FFFFFF","borderWidth":2}]},"options":{"responsive":true,"plugins":{"legend":{"display":true,"position":"right"},"title":{"display":true,"text":"Helsinki Population by Area"}}}}
Doughnut:
  {"type":"doughnut","data":{"labels":["A","B","C"],"datasets":[{"data":[30,50,20]}]}}
Radar:
  {"type":"radar","data":{"labels":["A","B","C"],"datasets":[{"data":[10,20,15]}]}}
*/
