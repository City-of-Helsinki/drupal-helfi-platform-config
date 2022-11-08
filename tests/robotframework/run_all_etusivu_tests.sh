if [[ ! -n "$PREFIX" ]]; then
  PREFIX=""
fi

if [[ ! -n "$BASE_URL" ]]; then
  BASE_URL="varnish-helfi-etusivu.docker.so"
fi

echo
echo "#######################################"
echo "# Running portfolio a first time      #"
echo "#######################################"
echo
pabot --testlevelsplit --processes 9 -i ETUSIVU_SPESIFIC -v PREFIX:$PREFIX -v BASE_URL:$BASE_URL -v PICCOMPARE:False -v useoriginalname:False -v images_dir:robotframework-resources/screenshots/headlesschrome -v actual_dir:robotframework-reports -A ./environments/ci.args -d robotframework-reports . $@
EXIT_CODE=$?

# we stop the script here if all the tests were OK
if [ $EXIT_CODE -eq 0 ]; then
	echo "we don't run the tests again as everything was OK on first try"
	exit 0
fi
# otherwise we go for another round with the failing tests

# we keep a copy of the first log file
cp robotframework-reports/log.html  robotframework-reports/first_run_log.html

# we launch the tests that failed
echo
echo "#######################################"
echo "# Running again the tests that failed #"
echo "#######################################"
echo
pabot --processes 9 -i ETUSIVU_SPESIFIC -v PREFIX:$PREFIX -v BASE_URL:$BASE_URL -v PICCOMPARE:False -v useoriginalname:False -v images_dir:robotframework-resources/screenshots/headlesschrome -v actual_dir:robotframework-reports -A ./environments/ci.args --rerunfailed robotframework-reports/output.xml --output rerun.xml -d robotframework-reports . $@
EXIT_CODE2=$?

# => Robot Framework generates file rerun.xml

# we keep a copy of the second log file
cp robotframework-reports/log.html  robotframework-reports/second_run_log.html

# Merging output files
echo
echo "########################"
echo "# Merging output files #"
echo "########################"
echo
rebot --nostatusrc --outputdir robotframework-reports --output output.xml --merge robotframework-reports/output.xml  robotframework-reports/rerun.xml
# => Robot Framework generates a new output.xml

exit $EXIT_CODE2
