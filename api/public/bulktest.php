<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

// This script handles the various deploy/undeploy actions from questiontestrun.php.
//
// @copyright  2023 University of Edinburgh
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
require_once('../config.php');
require_once(__DIR__ . '../../emulation/MoodleEmulation.php');
// Required to pass Moodle code check. Uses emulation stub.
require_login();
?>
<html>
  <head>
    <meta charset="utf-8"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
    <style>
      .feedback {
        color: black;
        background-color: #fcf2d4;
        border-radius: 4px;
        border: 1px solid #7d5a2933;
        padding: 10px;
        margin-left: 10px;
        width: fit-content;
      }
      .passed {
        background-color: lightgreen;
      }
      .failed {
        background-color: pink;
      }
      .seed {
        color: darkblue;
      }
      a.nav-link:link, a.nav-link:visited, a.nav-link:hover, a.nav-link:active {
        color:black;
        text-decoration:none;
      }
    </style>
  </head>
  <body>
    <script>
      function send(filepath, questionxml) {
        const http = new XMLHttpRequest();
        const url = "http://localhost:3080/test";
        http.open("POST", url, true);
        http.setRequestHeader('Content-Type', 'application/json');
        const pathArray = filepath.split('/');
        let currentDiv = '';
        for (const part of pathArray) {
          let prevDiv = currentDiv;
          currentDiv += (currentDiv) ? '/' : '';
          currentDiv += part;
          if (!document.getElementById(currentDiv)) {
            const newDivEl = document.createElement("div");
            newDivEl.setAttribute('id', currentDiv);
            newDivEl.innerHTML = '<h5>' + currentDiv + '</h5>';
            if (prevDiv) {
              const prevDivEl = document.getElementById(prevDiv);
              newDivEl.setAttribute('style', 'margin-left: 10px;');
              prevDivEl.appendChild(newDivEl);
            } else {
              document.getElementById('output').appendChild(newDivEl);
            }
          }
        }
        http.onreadystatechange = function() {
          if(http.readyState == 4) {
            const resultDiv = document.createElement("div");
            resultDiv.setAttribute('style', 'margin-left: 10px;');
            try {
              const json = JSON.parse(http.responseText);
              if (json.error) {
                resultDiv.innerText = json.error + ' - JSON: ' + http.responseText;
                resultDiv.innerHTML += '<br><br>';
                document.getElementById('errors').appendChild(resultDiv);
                document.getElementById('errors').removeAttribute('hidden');
              } else {
                let resultHtml = '<h3>' + json.name + '</h3>';
                resultDiv.setAttribute('id', json.name);

                resultHtml += (json.isgeneneralfeedback) ? '' : '<p class="feedback"><?=stack_string('bulktestnogeneralfeedback')?></p>';
                resultHtml += (json.isupgradeerror) ? '<p class="feedback">' + json.results['noseed'].message + '</p>' : '';
                resultHtml += (json.istests) ? '' : '<p class="feedback"><?=stack_string('bulktestnotests')?></p>';
                resultHtml += (json.israndomvariants && !json.isdeployedseeds) ? '<p class="feedback"><?=stack_string('bulktestnodeployedseeds')?></p>' : '';
                for (seed in json.results) {
                  if (seed !== 'noseed') {
                    resultHtml += '<h5 class="seed">Seed ' + seed + '</h5>';
                  }
                  if (json.istests && json.results[seed].passes !== null) {
                    resultHtml += '<p class="feedback' + ((json.results[seed].fails === 0) ? ' passed' : ' failed') +  '">' + json.results[seed].passes + ' passes and ' + json.results[seed].fails + ' failures.</p>';
                  }
                  if (json.results[seed].messages) {
                    resultHtml += '<p class="feedback failed">' + json.results[seed].messages + '</p>';
                  }
                }
                resultDiv.innerHTML = resultHtml;
                const parentDivEl = document.getElementById(json.filepath);
                parentDivEl.appendChild(resultDiv);
                parentDivEl.replaceChildren(...Array.from(parentDivEl.children).sort((a,b) => a.id.localeCompare(b.id)));
              }
            } catch(e) {
              resultDiv.innerText = e.message + ' - JSON: ' + http.responseText;
              resultDiv.innerHTML += '<br><br>';
              document.getElementById('errors').appendChild(resultDiv);
              document.getElementById('errors').removeAttribute('hidden');
            }
          }
        };
        http.send(JSON.stringify({'questionDefinition': questionxml, 'filepath': filepath}));
      }

      function getLocalQuestionFile(file) {
        if (file) {
          const reader = new FileReader();
          reader.readAsText(file, "UTF-8");
          reader.onload = function (evt) {
            sendQuestionsFromFile(file.webkitRelativePath, evt.target.result);
          }
        }
      }

      function testFolder() {
        document.getElementById('output').innerHTML = '';
        let files = document.getElementById('local-folder').files;
        files = Array.from(files).sort((a,b) => a.webkitRelativePath.localeCompare(b.webkitRelativePath))
        for (const file of files) {
          if (file.type === 'application/xml' || file.type === 'text/xml')
            getLocalQuestionFile(file);

        }
      }

      function sendQuestionsFromFile(filepath, fileContents) {
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(fileContents, "text/xml");
        let questions = xmlDoc.getElementsByTagName("question");
        for (const question of questions) {
          if (question.getAttribute('type').toLowerCase() === 'stack') {
            send(filepath, '<quiz>\n' + question.outerHTML + '\n</quiz>');
          }
        }
      }

    </script>

    <div class="container-fluid que stack">
      <div class="vstack gap-3 ms-3 col-lg-8">
        <div>
          <a href="https://stack-assessment.org/" class="nav-link">
            <span style="display: flex; align-items: center; font-size: 20px">
              <span style="display: flex; align-items: center;">
                <img src="logo_large.png" style="height: 50px;">
                <span style="font-size: 50px;"><b>STACK </b></span>
              </span>
              &nbsp;| Online assessment
            </span>
          </a>
        </div>
        Choose a STACK folder:
        <input type="file" id="local-folder" accept=".xml" name="local-folder" webkitdirectory directory multiple/>
        <div>
          <input type="button" onclick="testFolder()" class="btn btn-primary" value="Test"/>
        </div>
      </div>
      <br>
      <div id='output'>
      </div>
      <div id='errors' class= 'feedback failed'hidden>
        <h1>
          Errors
        </h1>
        <br>
      </div>
    <br>

  </body>
</html>

