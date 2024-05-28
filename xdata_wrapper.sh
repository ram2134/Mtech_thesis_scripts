#!/bin/bash 


SCRIPT=`basename $0`
LANGID=xdata-lang # used by grep; kept for consistency with unicomp.sh

function help() {
 echo "Usage: "
 echo "$SCRIPT <input-file> [options]"
 echo "    [options] are the options to be passed to the native compiler"
 echo "    Only a single <input-file> is supported"
 echo 
}

function compileXDATA() {
    # For xdata, we just need to set up the files
    # to be sent as input to xdata_script.sh 
    HSFILE=${FILE}.sql 
    
    EXEFILE=${FILE}.out

    XDATA="/var/www/app/compilers/xdata_script.sh"


    QUERY_FILE=${FILE}"_query.sql"
    SCHEMA_FILE=${FILE}"_SchemaFile.sql"
    SAMPLE_DATA_FILE=${FILE}"_SampleData.sql"
    
    echo "bash $XDATA -c $SCHEMA_FILE -s $SAMPLE_DATA_FILE -q $QUERY_FILE -m ${HSFILE}" > ${EXEFILE} 

  # cat > $EXEFILE << EOF
#bash $XDATA -c $SCHEMA_FILE -s $SAMPLE_DATA_FILE -q $QUERY_FILE -m ${HSFILE} 
#EOF
   chmod +x $EXEFILE
}

if [ $# -eq 0 ]; then
    help;
    exit;
fi

FILEXT=$1          # first argument is the file name
EXT=".sql"
FILE=`basename ${FILEXT} ${EXT}` # ignore the extension

shift   # $@ is the [options] part now!
ARGS="$@"

# the Compilation
compileXDATA; status=$?

# The following is important for Prutor to know that 
# compilation has failed!
if [ $status -eq 0 ]; then
    printf "success: Compilation Successful\n" > /dev/stderr
else 
    printf "error: Compilation Terminated\n" > /dev/stderr
fi


