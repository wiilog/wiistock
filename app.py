import mysql.connector
import influxdb_client
from influxdb_client.client.write_api import SYNCHRONOUS
from concurrent.futures import ThreadPoolExecutor
import time
import re

def worker(min, max):

    print(f"Launch {min} to {max}")
    mydb = mysql.connector.connect(
        host="mysql",
        port=3306,
        user="root",
        password="root",
        database="local_clbprod"
    )

    mycursor = mydb.cursor()
    mycursor.execute("SELECT sensor_message.date AS date_message,"
                    "sensor_message.sensor_id AS sensor_id,"
                    "sensor_message.event AS event,"
                    "sensor_message.content AS content,"
                    "sensor_message.content_type AS content_type"
                " FROM sensor_message"
                f" WHERE sensor_id = 4 AND sensor_message.id >= {min} AND sensor_message.id < {max}")

    myresult = mycursor.fetchall()

    client = influxdb_client.InfluxDBClient(
        url="http://influxdb:8086",
        token="8z964dq6s84dq6s5d4s6q5f4d65f4fdsq7f98dqsf78",
        org="wiilog",
        bucket="wiistock_local"
    )

    # Write script
    write_api = client.write_api(write_options=SYNCHRONOUS)


    for x in myresult:
        date = x[0]
        sensor_id = x[1]
        event = x[2]
        content = x[3]
        content_type = x[4]
        p = influxdb_client.Point("sensor_message")
            .time(date)
            .tag("date", date.strftime("%Y-%m-%d %H:%M:%S"))
            .tag("sensor_id", sensor_id)
            .tag("event", event)
            .tag("content_type", content_type)
            .field("content", content)
        write_api.write(
            org="wiilog",
            bucket="wiistock_local",
             record=p)


with ThreadPoolExecutor(max_workers=15) as executor:
    for i in range(0, 1010):
        executor.submit(worker,
                        min=i*8500,
                        max=(i+1) * 8500)

