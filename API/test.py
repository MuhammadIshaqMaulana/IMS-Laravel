from flask import Flask, request, jsonify

app = Flask(__name__)

@app.route('/')
def home():
    return "TEMEYARO!ahijfbashvbksdbfljkdvvngkerjvg reifbjarhefkebgjaeosdfnskdjnjasnvadvndjknvkdnbrkbnjergkjaernlf;lakejf;oajov;dflvkfdsnkjrgfjknlevfdjbrbgasfkrvnv"

if __name__ == '__main__':
    app.run(debug=True)
