#!/bin/bash

# Set default values for variables.Here query file stores correct query-solution by instructor and mutant file contains student's submission
SCHEMA_FILE=""
SAMPLE_DATA_FILE=""
QUERY_FILE=""
MUTANT_FILE=""
XDATA_FILE_PATH="/XData-DataGen/XData/test/universityTest"
XDATA_SCHEMA_FILE="$XDATA_FILE_PATH/DDL.sql"
XDATA_SAMPLE_DATA_FILE="$XDATA_FILE_PATH/sampleData.sql"
XDATA_QUERY_FILE="$XDATA_FILE_PATH/queries.txt"
XDATA_MUTANT_FILE="$XDATA_FILE_PATH/mutants.txt"
XDATA_PROGRAM_PATH="/XData-DataGen/XData/test/RegressionTests.java"

# Define usage function
usage() {
  cat << EOF
Usage: $0 [-c schemaFile] [-s sampleDataFile] [-q queryfile] [-m mutantfile]
Takes input as path to 4 files in any order,the flags before the FilePath denotes the File Type.

  -h  opens flags descriptions
  -c  Specify the path to schema file.
  -s  Specify the path to sampledata file.
  -q  Specify the path to query file.
  -m Specify the path to mutant file.

EOF
}

# Parse options
while getopts "c:s:m:q:h" opt; do
  case $opt in
    c)
      
      SCHEMA_FILE=$OPTARG
     # echo "The schema file is : ".$SCHEMA_FILE
      ;;
    s)
      SAMPLE_DATA_FILE=$OPTARG
      #echo "The SampleData file is : ".$SAMPLE_DATA_FILE
      ;;
    m)
      MUTANT_FILE=$OPTARG
      #echo "The mutant file is : ".$MUTANT_FILE
      ;;
    q)
      QUERY_FILE=$OPTARG
      #echo "The query file is : ".$QUERY_FILE
      ;;
    h)
      usage
      exit 0
      ;;
    \?)
      echo "Invalid option: -$OPTARG" >&2
      usage >&2
      exit 1
      ;;
    :)
      echo "Option -$OPTARG requires an argument." >&2
      usage >&2
      exit 1
      ;;
  esac
done

# Shift to remove processed options from positional parameters
shift $((OPTIND-1))

# Check if required arguments are provided
if [[ -z $SCHEMA_FILE || -z $SAMPLE_DATA_FILE || -z $MUTANT_FILE || -z $QUERY_FILE  ]]; then
  echo "missing data "
  usage >&2
  exit 1
fi

# Copy contents of input files to output directory
cp "$SCHEMA_FILE" "$XDATA_SCHEMA_FILE"
cp "$SAMPLE_DATA_FILE" "$XDATA_SAMPLE_DATA_FILE"
cp "$MUTANT_FILE" "$XDATA_MUTANT_FILE"
cp "$QUERY_FILE" "$XDATA_QUERY_FILE"


# Run Java file
cd /XData-DataGen/XData
cd /XData-DataGen/XData; /usr/bin/env /usr/lib/jvm/jdk-17/bin/java @/tmp/t1.argfile test.RegressionTests



